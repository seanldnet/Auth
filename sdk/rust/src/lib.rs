use base64::{engine::general_purpose::STANDARD, Engine as _};
use rand::rngs::OsRng;
use reqwest::blocking::Client as HttpClient;
use reqwest::redirect::Policy;
use rsa::pkcs8::DecodePrivateKey;
use rsa::pss::BlindedSigningKey;
use rsa::signature::{RandomizedSigner, SignatureEncoding, Verifier};
use rsa::{RsaPrivateKey, RsaPublicKey};
use serde::{Deserialize, Serialize};
use sha2::{Digest, Sha256};
use std::io::Read;
use std::time::{SystemTime, UNIX_EPOCH};
use thiserror::Error;
use url::Url;

const MAX_RESPONSE_BYTES: u64 = 1024 * 1024;

#[derive(Debug, Error)]
pub enum LicenseError {
    #[error("invalid client configuration: {0}")]
    Configuration(String),
    #[error("request signing failed")]
    Signing,
    #[error("license API transport failed: {0}")]
    Transport(#[from] reqwest::Error),
    #[error("license API returned invalid JSON: {0}")]
    InvalidResponse(#[from] serde_json::Error),
    #[error("license API error ({status_code}): {message}")]
    Api { status_code: u16, message: String },
    #[error("license API response exceeds 1 MiB")]
    ResponseTooLarge,
    #[error("system clock is before Unix epoch")]
    Clock,
}

#[derive(Debug, Clone, Deserialize, Serialize)]
pub struct LicenseResult {
    pub valid: bool,
    pub status: String,
    #[serde(default)]
    pub license_no: Option<String>,
    #[serde(default)]
    pub activated_at: Option<String>,
    #[serde(default)]
    pub expires_at: Option<String>,
    #[serde(default)]
    pub device_label: Option<String>,
    #[serde(default)]
    pub machine_change_count: u32,
    #[serde(default)]
    pub idempotent: bool,
    #[serde(default)]
    pub message: Option<String>,
}

#[derive(Serialize)]
struct ApiEnvelope<T> {
    success: bool,
    data: Option<T>,
    message: Option<String>,
}

pub struct LicenseClient {
    base_url: Url,
    app_id: String,
    key_id: String,
    private_key: RsaPrivateKey,
    http: HttpClient,
}

impl LicenseClient {
    pub fn new(
        base_url: &str,
        app_id: impl Into<String>,
        key_id: impl Into<String>,
        private_key_pem: &str,
        timeout_seconds: u64,
    ) -> Result<Self, LicenseError> {
        let app_id = app_id.into();
        let key_id = key_id.into();
        if !valid_prefixed_hex(&app_id, "app_", 24, false) {
            return Err(LicenseError::Configuration("a valid software App ID is required".into()));
        }
        if !valid_prefixed_hex(&key_id, "key_", 16, true) {
            return Err(LicenseError::Configuration("a valid signing Key ID is required".into()));
        }
        let base_url = Url::parse(base_url)
            .map_err(|_| LicenseError::Configuration("base URL must be a valid HTTPS origin".into()))?;
        if base_url.scheme() != "https" || base_url.host_str().is_none()
            || base_url.path() != "/" || base_url.query().is_some() || base_url.fragment().is_some()
            || !base_url.username().is_empty() || base_url.password().is_some() {
            return Err(LicenseError::Configuration("base URL must be an HTTPS origin without path, query, or credentials".into()));
        }
        if !(1..=120).contains(&timeout_seconds) {
            return Err(LicenseError::Configuration("timeout must be between 1 and 120 seconds".into()));
        }
        let private_key = RsaPrivateKey::from_pkcs8_pem(private_key_pem)
            .map_err(|_| LicenseError::Configuration("unable to parse PKCS#8 RSA private key".into()))?;
        if private_key.n().bits() < 3072 {
            return Err(LicenseError::Configuration("RSA-3072 or stronger private key is required".into()));
        }
        let http = HttpClient::builder()
            .timeout(std::time::Duration::from_secs(timeout_seconds))
            .connect_timeout(std::time::Duration::from_secs(timeout_seconds.min(5)))
            .redirect(Policy::none())
            .https_only(true)
            .build()?;
        Ok(Self { base_url, app_id, key_id, private_key, http })
    }

    pub fn activate(&self, license_key: &str, fingerprint: &str, label: Option<&str>) -> Result<LicenseResult, LicenseError> {
        let mut payload = serde_json::json!({
            "app_id": self.app_id, "license_key": license_key,
            "device_fingerprint": fingerprint
        });
        if let Some(label) = label { payload["device_label"] = serde_json::Value::String(label.to_owned()); }
        self.post("/api/license/activate", payload)
    }

    pub fn verify(&self, license_key: &str, fingerprint: &str) -> Result<LicenseResult, LicenseError> {
        self.post("/api/license/verify", serde_json::json!({
            "app_id": self.app_id, "license_key": license_key,
            "device_fingerprint": fingerprint
        }))
    }

    pub fn change_device(&self, license_key: &str, current_fingerprint: &str,
        new_fingerprint: &str, label: Option<&str>) -> Result<LicenseResult, LicenseError> {
        let mut payload = serde_json::json!({
            "app_id": self.app_id, "license_key": license_key,
            "current_device_fingerprint": current_fingerprint,
            "new_device_fingerprint": new_fingerprint
        });
        if let Some(label) = label { payload["device_label"] = serde_json::Value::String(label.to_owned()); }
        self.post("/api/license/change-device", payload)
    }

    pub fn unbind(&self, license_key: &str, fingerprint: &str) -> Result<LicenseResult, LicenseError> {
        self.post("/api/license/unbind", serde_json::json!({
            "app_id": self.app_id, "license_key": license_key,
            "device_fingerprint": fingerprint
        }))
    }

    fn post(&self, path: &str, payload: serde_json::Value) -> Result<LicenseResult, LicenseError> {
        let body = serde_json::to_vec(&payload)?;
        let timestamp = SystemTime::now().duration_since(UNIX_EPOCH)
            .map_err(|_| LicenseError::Clock)?.as_secs().to_string();
        let nonce = random_nonce();
        let canonical = build_canonical_message("POST", path, &timestamp, &nonce, &body)?;
        let signer = BlindedSigningKey::<Sha256>::new_with_salt_len(self.private_key.clone(), 32);
        let signature = signer.sign_with_rng(&mut OsRng, canonical.as_bytes());
        let signature = STANDARD.encode(signature.to_bytes());

        let url = self.base_url.join(path.trim_start_matches('/'))
            .map_err(|_| LicenseError::Configuration("invalid API endpoint path".into()))?;
        let mut response = self.http.post(url)
            .header("Accept", "application/json")
            .header("Content-Type", "application/json")
            .header("X-App-Id", &self.app_id)
            .header("X-Key-Id", &self.key_id)
            .header("X-Timestamp", &timestamp)
            .header("X-Nonce", &nonce)
            .header("X-Signature", signature)
            .body(body)
            .send()?;

        if response.content_length().is_some_and(|length| length > MAX_RESPONSE_BYTES) {
            return Err(LicenseError::ResponseTooLarge);
        }
        let status = response.status();
        let mut bytes = Vec::new();
        response.take(MAX_RESPONSE_BYTES + 1).read_to_end(&mut bytes)
            .map_err(|error| LicenseError::Configuration(format!("unable to read API response: {error}")))?;
        if bytes.len() as u64 > MAX_RESPONSE_BYTES { return Err(LicenseError::ResponseTooLarge); }
        let envelope: ApiEnvelope<LicenseResult> = serde_json::from_slice(&bytes)?;
        if !status.is_success() || !envelope.success {
            return Err(LicenseError::Api {
                status_code: status.as_u16(),
                message: envelope.message.unwrap_or_else(|| "license API request failed".into()),
            });
        }
        envelope.data.ok_or_else(|| LicenseError::Api {
            status_code: status.as_u16(),
            message: "license API response is missing data".into(),
        })
    }
}

fn valid_prefixed_hex(value: &str, prefix: &str, length: usize, lowercase_only: bool) -> bool {
    value.strip_prefix(prefix).is_some_and(|suffix| suffix.len() == length
        && suffix.bytes().all(|byte| byte.is_ascii_hexdigit()
            && (!lowercase_only || !byte.is_ascii_uppercase())))
}

fn random_nonce() -> String {
    use rand::RngCore;
    let mut bytes = [0u8; 32];
    OsRng.fill_bytes(&mut bytes);
    hex::encode(bytes)
}

pub fn build_canonical_message(method: &str, path: &str, timestamp: &str,
    nonce: &str, body: &[u8]) -> Result<String, LicenseError> {
    if !path.starts_with('/') || path.contains('?') {
        return Err(LicenseError::Configuration("signed path must start with / and exclude query strings".into()));
    }
    let digest = Sha256::digest(body);
    Ok(format!("{}\n{}\n{}\n{}\n{}", method.to_ascii_uppercase(), path,
        timestamp, nonce, hex::encode(digest)))
}

#[cfg(test)]
mod tests {
    use super::*;
    use rsa::pss::VerifyingKey;
    use rsa::signature::{RandomizedSigner, Verifier};
    use rsa::RsaPublicKey;

    #[test]
    fn canonical_message_hashes_exact_body() {
        let body = br#"{"app_id":"app_123"}"#;
        let canonical = build_canonical_message("post", "/api/license/verify",
            "1795700000", "0123456789abcdef0123456789abcdef", body).unwrap();
        assert!(canonical.starts_with("POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n"));
        assert!(canonical.ends_with(&hex::encode(Sha256::digest(body))));
    }

    #[test]
    fn rsa_pss_signature_verifies_and_tampering_fails() {
        let private = RsaPrivateKey::new(&mut OsRng, 3072).unwrap();
        let public = RsaPublicKey::from(&private);
        let signing_key = BlindedSigningKey::<Sha256>::new_with_salt_len(private, 32);
        let message = b"signed request";
        let signature = signing_key.sign_with_rng(&mut OsRng, message);
        let verifying_key = VerifyingKey::<Sha256>::new_with_salt_len(public, 32);
        assert!(verifying_key.verify(message, &signature).is_ok());
        assert!(verifying_key.verify(b"tampered request", &signature).is_err());
    }

    #[test]
    fn canonical_path_rejects_query() {
        assert!(build_canonical_message("POST", "/api/license/verify?x=1", "1", "0123456789abcdef", b"").is_err());
    }
}
