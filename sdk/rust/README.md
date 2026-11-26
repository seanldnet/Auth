# Auth License SDK for Rust

Rust 1.75+ client for the Auth license runtime API. Uses RustCrypto RSA-PSS with SHA-256, MGF1-SHA256, a 32-byte salt, and reqwest with rustls TLS.

## Key generation

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkcs8 -topk8 -nocrypt -in client_private.pem -out client_private_pkcs8.pem
openssl pkey -in client_private_pkcs8.pem -pubout -out client_public.pem
```

Upload only the public PEM to the software signing-key settings. Keep the PKCS#8 private key in a protected secret store.

## Build and test

```bash
cargo test --all
cargo build --release
```

## Usage

```rust
use auth_license_sdk::LicenseClient;

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let pem = std::fs::read_to_string("/secure/path/client_private_pkcs8.pem")?;
    let client = LicenseClient::new(
        "https://license.example.com",
        "app_0123456789abcdef01234567",
        "key_0123456789abcdef",
        &pem,
        10,
    )?;
    let result = client.verify(
        "01234567-89ABCDEF-01234567-89ABCDEF",
        "stable-device-fingerprint",
    )?;
    if !result.valid {
        // Apply the application's license policy.
    }
    Ok(())
}
```

## Operations

- `activate(license_key, fingerprint, label)`
- `verify(license_key, fingerprint)`
- `change_device(license_key, current_fingerprint, new_fingerprint, label)`
- `unbind(license_key, fingerprint)`

The SDK requires an HTTPS origin, disables redirects, verifies TLS, and limits API response bodies to 1 MiB. API errors are returned as typed `LicenseError` variants.
