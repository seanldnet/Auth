# Auth License SDK for Kotlin / JVM

Kotlin 2.1+ coroutine-friendly SDK facade for the Auth license runtime API. It reuses the Java 17 SDK implementation so Java and Kotlin clients share the same RSA-PSS canonical request implementation, request headers, response models, and endpoint behavior.

## Build and test

Requires JDK 17+ and Gradle 8.10+.

```bash
gradle test
```

## Key generation

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only the public PEM to the software signing-key settings. Keep the PKCS#8 private key in a protected secret store.

## Usage

```kotlin
import com.authplatform.licensesdk.kotlin.KotlinLicenseClient
import kotlinx.coroutines.runBlocking

fun main() = runBlocking {
    val privatePem = java.io.File("/secure/path/client_private.pem").readText()
    val client = KotlinLicenseClient.fromPem(
        "https://license.example.com",
        "app_0123456789abcdef01234567",
        "key_0123456789abcdef",
        privatePem,
    )

    val result = client.verify(
        "01234567-89ABCDEF-01234567-89ABCDEF",
        "stable-device-fingerprint",
    )
    if (!result.valid()) {
        // Apply the application's license policy.
    }
}
```

## Operations

- `activate(licenseKey, deviceFingerprint, deviceLabel)`
- `verify(licenseKey, deviceFingerprint)`
- `changeDevice(licenseKey, currentFingerprint, newFingerprint, deviceLabel)`
- `unbind(licenseKey, deviceFingerprint)`

All four operations are suspending functions and execute blocking Java HTTP work on `Dispatchers.IO`. The facade delegates to the shared Java implementation to avoid protocol drift.
