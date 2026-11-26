import base64
import hashlib

import pytest
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import padding, rsa

from auth_license_sdk import build_canonical_message, sign_request


def test_canonical_message_hashes_exact_body_and_uppercases_method():
    body = b'{"app_id":"app_123"}'
    canonical = build_canonical_message(
        "post", "/api/license/verify", "1795700000", "0123456789abcdef0123456789abcdef", body
    )
    assert canonical.startswith(
        b"POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n"
    )
    assert canonical.endswith(hashlib.sha256(body).hexdigest().encode("ascii"))


def test_rsa_pss_signature_verifies_and_tampering_fails():
    private_key = rsa.generate_private_key(public_exponent=65537, key_size=3072)
    message = b"POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n" + b"a" * 64
    encoded = sign_request(private_key, message)
    signature = base64.b64decode(encoded)
    public_key = private_key.public_key()
    public_key.verify(
        signature, message,
        padding.PSS(mgf=padding.MGF1(hashes.SHA256()), salt_length=32),
        hashes.SHA256(),
    )
    with pytest.raises(Exception):
        public_key.verify(
            signature, message + b"x",
            padding.PSS(mgf=padding.MGF1(hashes.SHA256()), salt_length=32),
            hashes.SHA256(),
        )


def test_canonical_path_rejects_query_string():
    with pytest.raises(ValueError):
        build_canonical_message("POST", "/api/license/verify?x=1", "1", "1234567890123456", b"")
