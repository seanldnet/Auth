from __future__ import annotations

import base64
import hashlib
import json
import re
import secrets
import time
from dataclasses import dataclass
from typing import Any

import requests
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa
from cryptography.hazmat.primitives.asymmetric.padding import MGF1


@dataclass(frozen=True)
class LicenseResult:
    valid: bool
    status: str
    license_no: str | None = None
    activated_at: str | None = None
    expires_at: str | None = None
    device_label: str | None = None
    machine_change_count: int = 0
    idempotent: bool = False
    message: str | None = None


class LicenseApiError(RuntimeError):
    def __init__(self, status_code: int, message: str) -> None:
        super().__init__(message)
        self.status_code = status_code


def build_canonical_message(
    method: str, path: str, timestamp: str, nonce: str, body: bytes
) -> bytes:
    if not path.startswith("/") or "?" in path:
        raise ValueError("Signed path must start with / and must not contain a query string")
    body_hash = hashlib.sha256(body).hexdigest()
    message = "\n".join((method.upper(), path, timestamp, nonce, body_hash))
    return message.encode("utf-8")


def sign_request(private_key: rsa.RSAPrivateKey, canonical_message: bytes) -> str:
    signature = private_key.sign(
        canonical_message,
        padding.PSS(mgf=MGF1(hashes.SHA256()), salt_length=32),
        hashes.SHA256(),
    )
    return base64.b64encode(signature).decode("ascii")


class LicenseClient:
    def __init__(
        self,
        base_url: str,
        app_id: str,
        key_id: str,
        private_key_pem: str | bytes,
        *,
        timeout: float = 10.0,
        session: requests.Session | None = None,
    ) -> None:
        if not re.fullmatch(r"app_[a-fA-F0-9]{24}", app_id):
            raise ValueError("A valid software App ID is required")
        if not re.fullmatch(r"key_[a-f0-9]{16}", key_id):
            raise ValueError("A valid signing Key ID is required")
        pem = private_key_pem.encode("utf-8") if isinstance(private_key_pem, str) else private_key_pem
        key = serialization.load_pem_private_key(pem, password=None)
        if not isinstance(key, rsa.RSAPrivateKey) or key.key_size < 3072:
            raise ValueError("RSA-3072 or stronger private key is required")
        self._private_key = key
        self._app_id = app_id
        self._key_id = key_id
        self._base_url = base_url.rstrip("/")
        self._timeout = timeout
        self._owns_session = session is None
        self._session = session or requests.Session()

    def activate(self, license_key: str, device_fingerprint: str, device_label: str | None = None) -> LicenseResult:
        return self._post("/api/license/activate", {
            "app_id": self._app_id, "license_key": license_key,
            "device_fingerprint": device_fingerprint, "device_label": device_label,
        })

    def verify(self, license_key: str, device_fingerprint: str) -> LicenseResult:
        return self._post("/api/license/verify", {
            "app_id": self._app_id, "license_key": license_key,
            "device_fingerprint": device_fingerprint,
        })

    def change_device(
        self, license_key: str, current_device_fingerprint: str,
        new_device_fingerprint: str, device_label: str | None = None,
    ) -> LicenseResult:
        return self._post("/api/license/change-device", {
            "app_id": self._app_id, "license_key": license_key,
            "current_device_fingerprint": current_device_fingerprint,
            "new_device_fingerprint": new_device_fingerprint, "device_label": device_label,
        })

    def unbind(self, license_key: str, device_fingerprint: str) -> LicenseResult:
        return self._post("/api/license/unbind", {
            "app_id": self._app_id, "license_key": license_key,
            "device_fingerprint": device_fingerprint,
        })

    def _post(self, path: str, payload: dict[str, Any]) -> LicenseResult:
        body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        timestamp = str(int(time.time()))
        nonce = secrets.token_hex(32)
        canonical = build_canonical_message("POST", path, timestamp, nonce, body)
        signature = sign_request(self._private_key, canonical)
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-App-Id": self._app_id,
            "X-Key-Id": self._key_id,
            "X-Timestamp": timestamp,
            "X-Nonce": nonce,
            "X-Signature": signature,
        }
        response = self._session.post(
            self._base_url + path, data=body, headers=headers, timeout=self._timeout
        )
        try:
            envelope = response.json()
        except ValueError as exc:
            raise LicenseApiError(response.status_code, "License API returned invalid JSON") from exc
        if not response.ok or not envelope.get("success"):
            raise LicenseApiError(response.status_code, envelope.get("message") or "License API request failed")
        data = envelope.get("data") or {}
        return LicenseResult(
            valid=bool(data.get("valid", False)),
            status=str(data.get("status", "UNKNOWN")),
            license_no=data.get("license_no"),
            activated_at=data.get("activated_at"),
            expires_at=data.get("expires_at"),
            device_label=data.get("device_label"),
            machine_change_count=int(data.get("machine_change_count", 0)),
            idempotent=bool(data.get("idempotent", False)),
            message=data.get("message"),
        )

    def close(self) -> None:
        if self._owns_session:
            self._session.close()

    def __enter__(self) -> "LicenseClient":
        return self

    def __exit__(self, *_: object) -> None:
        self.close()
