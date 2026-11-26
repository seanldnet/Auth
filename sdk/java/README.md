# Auth License SDK for Java 17+

Java 17 client for the Auth license runtime API. Uses JCA RSA-PSS with SHA-256, MGF1-SHA256, and a 32-byte salt.

## Key generation

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only the public PEM to the software signing-key section. Keep the PKCS#8 private key in a protected secret store. The SDK accepts `BEGIN PRIVATE KEY` PKCS#8 PEM.

## Maven

```bash
mvn test
```

## Usage

```java
import com.authplatform.licensesdk.LicenseClient;
import com.authplatform.licensesdk.LicenseResult;

import java.net.URI;
import java.nio.file.Files;
import java.nio.file.Path;

String privatePem = Files.readString(Path.of("/secure/path/client_private.pem"));
LicenseClient client = new LicenseClient(
    URI.create("https://license.example.com"),
    "app_0123456789abcdef01234567",
    "key_0123456789abcdef",
    privatePem
);

LicenseResult result = client.verify(
    "01234567-89ABCDEF-01234567-89ABCDEF",
    "stable-device-fingerprint"
);
if (!result.valid()) {
    // Apply the application's license policy.
}
```

## Operations

- `activate(licenseKey, fingerprint, deviceLabel)`
- `verify(licenseKey, fingerprint)`
- `changeDevice(licenseKey, currentFingerprint, newFingerprint, deviceLabel)`
- `unbind(licenseKey, fingerprint)`

The API methods throw `LicenseApiException` for rejected HTTP/API responses and propagate IO/interruption/security exceptions. Use HTTPS and do not log private key material, license keys, or signatures.
