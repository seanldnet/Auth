-- Add HMAC request signing and replay protection without invalidating existing bearer keys.
-- Existing keys have NULL secret_ciphertext and continue to use the legacy bearer flow.
ALTER TABLE auth_api_keys
    ADD COLUMN secret_ciphertext TEXT NULL AFTER key_hash;

CREATE TABLE IF NOT EXISTS auth_api_key_nonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_key_id BIGINT UNSIGNED NOT NULL,
    nonce VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_auth_api_key_nonce (api_key_id, nonce),
    KEY idx_auth_api_key_nonce_expiry (expires_at),
    CONSTRAINT fk_auth_api_key_nonce_key FOREIGN KEY (api_key_id)
        REFERENCES auth_api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
