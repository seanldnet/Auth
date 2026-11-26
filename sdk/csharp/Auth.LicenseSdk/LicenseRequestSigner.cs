using System.Security.Cryptography;
using System.Text;

namespace Auth.LicenseSdk;

/// <summary>Builds the canonical request and RSA-PSS signature required by the Auth API.</summary>
public static class LicenseRequestSigner
{
    public static string BuildCanonicalMessage(string method, string path, string timestamp, string nonce, ReadOnlySpan<byte> body)
    {
        if (string.IsNullOrWhiteSpace(method)) throw new ArgumentException("HTTP method is required.", nameof(method));
        if (string.IsNullOrWhiteSpace(path) || !path.StartsWith("/", StringComparison.Ordinal) || path.Contains('?'))
            throw new ArgumentException("Path must start with / and must not contain a query string.", nameof(path));
        var bodyHash = Convert.ToHexString(SHA256.HashData(body)).ToLowerInvariant();
        return string.Join("\n", method.ToUpperInvariant(), path, timestamp, nonce, bodyHash);
    }

    public static byte[] Sign(RSA privateKey, string canonicalMessage)
    {
        ArgumentNullException.ThrowIfNull(privateKey);
        return privateKey.SignData(Encoding.UTF8.GetBytes(canonicalMessage), HashAlgorithmName.SHA256, RSASignaturePadding.Pss);
    }
}
