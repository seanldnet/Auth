# Auth License SDK for C11

C11 client for the Auth license runtime API. Uses OpenSSL 3 for RSA-PSS / SHA-256, libcurl for HTTPS transport, and json-c for JSON payloads.

## Requirements

- C11 compiler and CMake 3.20+
- OpenSSL 3.0+
- libcurl
- json-c
- pthreads

Ubuntu/Debian:

```bash
sudo apt-get install build-essential cmake libssl-dev libcurl4-openssl-dev libjson-c-dev pkg-config
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

Upload only the public key PEM. Protect the private key and never embed it in a public binary distributed to untrusted users.

## Usage

```c
#include <stdio.h>
#include <stdlib.h>
#include "auth_license.h"

int main(void) {
    char *error = NULL;
    auth_license_client *client = auth_license_client_new(
        "https://license.example.com",
        "app_0123456789abcdef01234567",
        "key_0123456789abcdef",
        private_key_pem,
        10,
        &error
    );
    if (client == NULL) {
        fprintf(stderr, "%s\n", error);
        auth_license_free_string(error);
        return 1;
    }

    long status = 0;
    char *result = auth_license_verify(client,
        "01234567-89ABCDEF-01234567-89ABCDEF",
        "stable-device-fingerprint", &status, &error);
    if (result == NULL) {
        fprintf(stderr, "HTTP %ld: %s\n", status, error ? error : "request failed");
    } else {
        puts(result); /* JSON data object */
        auth_license_free_string(result);
    }
    auth_license_free_string(error);
    auth_license_client_free(client);
    return result == NULL ? 1 : 0;
}
```

## Operations

- `auth_license_activate`
- `auth_license_verify`
- `auth_license_change_device`
- `auth_license_unbind`

All result/error strings are heap allocated and must be released with `auth_license_free_string`. The client requires an HTTPS origin, verifies TLS certificates, disables redirects, and limits response bodies to 1 MiB. Requests use a fresh 256-bit nonce and the platform's RSA-PSS / SHA-256 canonical signing format.
