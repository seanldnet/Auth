using System.Globalization;
using System.Net;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace Auth.LicenseSdk;

/// <summary>RSA-PSS authenticated client for license activation and device operations.</summary>
public sealed class LicenseClient : IDisposable
{
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web);
    private readonly HttpClient _httpClient;
    private readonly RSA _privateKey;
    private readonly string _appId;
    private readonly string _keyId;
    private readonly bool _ownsHttpClient;

    public LicenseClient(HttpClient httpClient, string appId, string keyId, string privateKeyPem)
        : this(httpClient, appId, keyId, ImportPrivateKey(privateKeyPem), false)
    {
    }

    private LicenseClient(HttpClient httpClient, string appId, string keyId, RSA privateKey, bool ownsHttpClient)
    {
        ArgumentNullException.ThrowIfNull(httpClient);
        if (string.IsNullOrWhiteSpace(appId) || !System.Text.RegularExpressions.Regex.IsMatch(appId, @"^app_[a-fA-F0-9]{24}$"))
            throw new ArgumentException("A valid software App ID is required.", nameof(appId));
        if (string.IsNullOrWhiteSpace(keyId) || !System.Text.RegularExpressions.Regex.IsMatch(keyId, @"^key_[a-f0-9]{16}$"))
            throw new ArgumentException("A valid signing Key ID is required.", nameof(keyId));
        if (privateKey.KeySize < 3072)
            throw new ArgumentException("RSA-3072 or stronger private key is required.", nameof(privateKey));

        _httpClient = httpClient;
        _appId = appId;
        _keyId = keyId;
        _privateKey = privateKey;
        _ownsHttpClient = ownsHttpClient;
    }

    /// <summary>Creates a client with an internally owned HttpClient.</summary>
    public static LicenseClient Create(string baseUrl, string appId, string keyId, string privateKeyPem)
    {
        var http = new HttpClient { BaseAddress = new Uri(baseUrl, UriKind.Absolute) };
        return new LicenseClient(http, appId, keyId, ImportPrivateKey(privateKeyPem), true);
    }

    public Task<LicenseResult> ActivateAsync(string licenseKey, string deviceFingerprint, string? deviceLabel = null, CancellationToken cancellationToken = default)
        => SendAsync<LicenseResult>("/api/license/activate", new ActivateRequest(_appId, licenseKey, deviceFingerprint, deviceLabel), cancellationToken);

    public Task<LicenseResult> VerifyAsync(string licenseKey, string deviceFingerprint, CancellationToken cancellationToken = default)
        => SendAsync<LicenseResult>("/api/license/verify", new VerifyRequest(_appId, licenseKey, deviceFingerprint), cancellationToken);

    public Task<LicenseResult> ChangeDeviceAsync(string licenseKey, string currentDeviceFingerprint, string newDeviceFingerprint, string? deviceLabel = null, CancellationToken cancellationToken = default)
        => SendAsync<LicenseResult>("/api/license/change-device", new ChangeDeviceRequest(_appId, licenseKey, currentDeviceFingerprint, newDeviceFingerprint, deviceLabel), cancellationToken);

    public Task<LicenseResult> UnbindAsync(string licenseKey, string deviceFingerprint, CancellationToken cancellationToken = default)
        => SendAsync<LicenseResult>("/api/license/unbind", new UnbindRequest(_appId, licenseKey, deviceFingerprint), cancellationToken);

    private async Task<T> SendAsync<T>(string path, object payload, CancellationToken cancellationToken)
    {
        if (path.Contains('?') || !path.StartsWith("/", StringComparison.Ordinal))
            throw new ArgumentException("Signed API path must be absolute and must not include a query string.", nameof(path));

        var body = JsonSerializer.SerializeToUtf8Bytes(payload, JsonOptions);
        var timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString(CultureInfo.InvariantCulture);
        var nonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(32)).ToLowerInvariant();
        var canonical = LicenseRequestSigner.BuildCanonicalMessage("POST", path, timestamp, nonce, body);
        var signature = LicenseRequestSigner.Sign(_privateKey, canonical);

        using var request = new HttpRequestMessage(HttpMethod.Post, path);
        request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        request.Headers.TryAddWithoutValidation("X-App-Id", _appId);
        request.Headers.TryAddWithoutValidation("X-Key-Id", _keyId);
        request.Headers.TryAddWithoutValidation("X-Timestamp", timestamp);
        request.Headers.TryAddWithoutValidation("X-Nonce", nonce);
        request.Headers.TryAddWithoutValidation("X-Signature", Convert.ToBase64String(signature));
        request.Content = new ByteArrayContent(body);
        request.Content.Headers.ContentType = new MediaTypeHeaderValue("application/json");

        using var response = await _httpClient.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, cancellationToken).ConfigureAwait(false);
        await using var stream = await response.Content.ReadAsStreamAsync(cancellationToken).ConfigureAwait(false);
        var envelope = await JsonSerializer.DeserializeAsync<ApiEnvelope<T>>(stream, JsonOptions, cancellationToken).ConfigureAwait(false);
        if (!response.IsSuccessStatusCode || envelope is null || !envelope.Success || envelope.Data is null)
            throw new LicenseApiException(response.StatusCode, envelope?.Message ?? "License API request failed.");
        return envelope.Data;
    }

    private static RSA ImportPrivateKey(string pem)
    {
        if (string.IsNullOrWhiteSpace(pem)) throw new ArgumentException("RSA private key PEM is required.", nameof(pem));
        var rsa = RSA.Create();
        try
        {
            rsa.ImportFromPem(pem);
            if (rsa.KeySize < 3072) throw new ArgumentException("RSA-3072 or stronger private key is required.", nameof(pem));
            _ = rsa.ExportParameters(true);
            return rsa;
        }
        catch
        {
            rsa.Dispose();
            throw;
        }
    }

    public void Dispose()
    {
        _privateKey.Dispose();
        if (_ownsHttpClient) _httpClient.Dispose();
    }

    private sealed record ApiEnvelope<T>(
        [property: JsonPropertyName("success")] bool Success,
        [property: JsonPropertyName("data")] T? Data,
        [property: JsonPropertyName("message")] string? Message);

    private sealed record ActivateRequest(
        [property: JsonPropertyName("app_id")] string AppId,
        [property: JsonPropertyName("license_key")] string LicenseKey,
        [property: JsonPropertyName("device_fingerprint")] string DeviceFingerprint,
        [property: JsonPropertyName("device_label")] string? DeviceLabel);

    private sealed record VerifyRequest(
        [property: JsonPropertyName("app_id")] string AppId,
        [property: JsonPropertyName("license_key")] string LicenseKey,
        [property: JsonPropertyName("device_fingerprint")] string DeviceFingerprint);

    private sealed record ChangeDeviceRequest(
        [property: JsonPropertyName("app_id")] string AppId,
        [property: JsonPropertyName("license_key")] string LicenseKey,
        [property: JsonPropertyName("current_device_fingerprint")] string CurrentDeviceFingerprint,
        [property: JsonPropertyName("new_device_fingerprint")] string NewDeviceFingerprint,
        [property: JsonPropertyName("device_label")] string? DeviceLabel);

    private sealed record UnbindRequest(
        [property: JsonPropertyName("app_id")] string AppId,
        [property: JsonPropertyName("license_key")] string LicenseKey,
        [property: JsonPropertyName("device_fingerprint")] string DeviceFingerprint);
}

/// <summary>Represents a failed or rejected license API response.</summary>
public sealed class LicenseApiException(HttpStatusCode statusCode, string message) : Exception(message)
{
    public HttpStatusCode StatusCode { get; } = statusCode;
}

/// <summary>Normalized result returned by license runtime endpoints.</summary>
public sealed record LicenseResult(
    [property: JsonPropertyName("valid")] bool Valid,
    [property: JsonPropertyName("license_no")] string? LicenseNo,
    [property: JsonPropertyName("status")] string Status,
    [property: JsonPropertyName("activated_at")] string? ActivatedAt,
    [property: JsonPropertyName("expires_at")] string? ExpiresAt,
    [property: JsonPropertyName("device_label")] string? DeviceLabel,
    [property: JsonPropertyName("machine_change_count")] int MachineChangeCount,
    [property: JsonPropertyName("idempotent")] bool Idempotent,
    [property: JsonPropertyName("message")] string? Message);
