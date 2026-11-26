# Auth License SDK for Python

Python 3.10+ client for the Auth license runtime API. The client signs each request with RSA-PSS / SHA-256, MGF1-SHA256 and a 32-byte salt, then sends the exact JSON bytes covered by the signature.

## Install

```bash
python -m pip install .
```

For development and tests:

```bash
python -m pip install -e ".[test]"
pytest
```

## Generate and register a key

Generate a 3072-bit RSA key pair locally:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only `client_public.pem` in the software's RSA-PSS signing key section. Keep the private key in a secret manager or protected local file; never commit it or upload it to the platform.

## Usage

```python
from pathlib import Path
from auth_license_sdk import LicenseClient, LicenseApiError

private_key = Path("/secure/path/client_private.pem").read_text(encoding="utf-8")

with LicenseClient(
    "https://license.example.com",
    "app_0123456789abcdef01234567",
    "key_0123456789abcdef",
    private_key,
) as client:
    result = client.activate(
        "01234567-89ABCDEF-01234567-89ABCDEF",
        device_fingerprint="stable-device-fingerprint",
        device_label="Workstation",
    )
    if result.valid:
        print("License active:", result.license_no)
```

## Operations

- `activate(license_key, device_fingerprint, device_label=None)`
- `verify(license_key, device_fingerprint)`
- `change_device(license_key, current_device_fingerprint, new_device_fingerprint, device_label=None)`
- `unbind(license_key, device_fingerprint)`

API failures raise `LicenseApiError` with an HTTP `status_code`. Network and timeout exceptions from Requests are propagated. Each SDK method creates a fresh timestamp and nonce; never replay a previously signed HTTP request.
