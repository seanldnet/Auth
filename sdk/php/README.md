# Auth License SDK for PHP 8.2+

PHP client for the Auth license runtime API. It uses phpseclib 3 to implement RSA-PSS with SHA-256, MGF1-SHA256, and a 32-byte salt. PHP's built-in OpenSSL signing API does not expose the required PSS salt configuration consistently, so this SDK uses phpseclib directly.

## Requirements

- PHP 8.2+
- ext-curl and ext-json
- HTTPS endpoint
- Composer

## Install

```bash
composer require auth-platform/license-sdk
```

When consuming from this monorepo, run `composer install` inside `sdk/php`.

## Generate a client key

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only the public PEM in the software signing-key settings. Keep the private key outside source control and public web roots.

## Usage

```php
use AuthPlatform\LicenseSdk\LicenseClient;
use AuthPlatform\LicenseSdk\LicenseApiException;

$privateKeyPem = file_get_contents('/secure/path/client_private.pem');
$client = new LicenseClient(
    'https://license.example.com',
    'app_0123456789abcdef01234567',
    'key_0123456789abcdef',
    $privateKeyPem,
);

try {
    $result = $client->verify(
        '01234567-89ABCDEF-01234567-89ABCDEF',
        'stable-device-fingerprint',
    );
    if (!$result['valid']) {
        // Apply the application's license policy.
    }
} catch (LicenseApiException $exception) {
    // Handle a rejected API response using $exception->statusCode.
}
```

## Operations

- `activate($licenseKey, $deviceFingerprint, $deviceLabel = null)`
- `verify($licenseKey, $deviceFingerprint)`
- `changeDevice($licenseKey, $currentFingerprint, $newFingerprint, $deviceLabel = null)`
- `unbind($licenseKey, $deviceFingerprint)`

Each request signs the exact JSON bytes sent over HTTP. A fresh 256-bit nonce and Unix timestamp are generated per request. TLS certificate verification is enabled, redirects are disabled, and response bodies are limited to 1 MiB.
