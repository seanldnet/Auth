# Auth.LicenseSdk (.NET 8)

A small .NET 8 client for the Auth license runtime API. It signs every request with RSA-PSS / SHA-256, uses a fresh 256-bit nonce, and sends the exact UTF-8 JSON bytes that were signed.

## Install / reference

The SDK project currently lives at `sdk/csharp/Auth.LicenseSdk`. Add a project reference or package the project into your internal NuGet feed.

## Create a client

Generate an RSA-3072 (or stronger) key pair locally:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only `client_public.pem` in the software's signing-key section. Keep the private key outside source control and load it from a protected secret store.

```csharp
using Auth.LicenseSdk;

var privateKeyPem = await File.ReadAllTextAsync("/secure/path/client_private.pem");
using var client = LicenseClient.Create(
    baseUrl: "https://license.example.com",
    appId: "app_0123456789abcdef01234567",
    keyId: "key_0123456789abcdef",
    privateKeyPem: privateKeyPem);

var activated = await client.ActivateAsync(
    licenseKey: "01234567-89ABCDEF-01234567-89ABCDEF",
    deviceFingerprint: deviceFingerprint,
    deviceLabel: Environment.MachineName);

var validation = await client.VerifyAsync(
    licenseKey: "01234567-89ABCDEF-01234567-89ABCDEF",
    deviceFingerprint: deviceFingerprint);

if (!validation.Valid)
{
    // Apply the application's license policy.
}
```

## Supported operations

- `ActivateAsync`: activate an unused license and bind the current device.
- `VerifyAsync`: verify license status, expiration, and device fingerprint.
- `ChangeDeviceAsync`: move a binding from the current fingerprint to a new fingerprint.
- `UnbindAsync`: remove the current device binding.

The server allows a maximum clock skew of five minutes. Each request gets a unique nonce; do not retry a captured signed HTTP request. Call the SDK method again to create a fresh timestamp, nonce, and signature.

## Exceptions

Non-success HTTP responses or `success: false` API envelopes throw `LicenseApiException`, which includes the HTTP status code. Network and cancellation exceptions from `HttpClient` are propagated.
