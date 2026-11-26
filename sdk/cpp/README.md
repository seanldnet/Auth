# Auth License SDK for C++17

C++17 client for the Auth license runtime API. Uses OpenSSL 3 for RSA-PSS / SHA-256 (MGF1-SHA256, 32-byte salt), libcurl for HTTPS transport, and nlohmann/json for request/response JSON.

## Dependencies

- CMake 3.20+
- C++17 compiler
- OpenSSL 3.0+
- libcurl
- nlohmann/json 3.10+

Ubuntu/Debian:

```bash
sudo apt-get install build-essential cmake libssl-dev libcurl4-openssl-dev nlohmann-json3-dev
```

## Build and test

```bash
cmake -S . -B build -DCMAKE_BUILD_TYPE=Release
cmake --build build --parallel
ctest --test-dir build --output-on-failure
```

## Key generation

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only the public PEM to the software signing-key settings. Never embed a private key in a public client binary or commit it to source control.

## Usage

```cpp
#include <fstream>
#include <sstream>
#include "auth_license/license_client.hpp"

int main() {
    std::ifstream file("/secure/path/client_private.pem");
    std::stringstream pem;
    pem << file.rdbuf();

    auth_license::LicenseClient client(
        "https://license.example.com",
        "app_0123456789abcdef01234567",
        "key_0123456789abcdef",
        pem.str()
    );

    const auto result = client.verify(
        "01234567-89ABCDEF-01234567-89ABCDEF",
        "stable-device-fingerprint"
    );
    if (!result.value("valid", false)) {
        // Apply the application's license policy.
    }
}
```

## Operations

- `activate(license_key, device_fingerprint, device_label)`
- `verify(license_key, device_fingerprint)`
- `change_device(license_key, current_fingerprint, new_fingerprint, device_label)`
- `unbind(license_key, device_fingerprint)`

The client requires an HTTPS origin, verifies TLS peer/host certificates, disables redirects, and limits response bodies to 1 MiB. API rejections throw `LicenseApiError` with an HTTP status code.
