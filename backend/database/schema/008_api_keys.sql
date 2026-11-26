-- Administrator / agent API key credentials.
-- Secrets are encrypted with APP_KEY (AES-256-GCM); plaintext is returned only once at creation.
CREATE TABLE IF NOT EXISTS auth_api_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_id VARCHAR(32) NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    secret_ciphertext TEXT NOT NULL,
    permissions JSON NOT NULL,
    ip_allowlist JSON NULL,
    status ENUM('ACTIVE','REVOKED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_auth_api_key_id (key_id),
    KEY idx_auth_api_keys_owner_status (owner_user_id, status, created_at),
    KEY idx_auth_api_keys_expiry (status, expires_at),
    CONSTRAINT fk_auth_api_keys_owner FOREIGN KEY (owner_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_api_key_nonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_key_id BIGINT UNSIGNED NOT NULL,
    nonce VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_auth_api_key_nonce (api_key_id, nonce),
    KEY idx_auth_api_key_nonce_expiry (expires_at),
    CONSTRAINT fk_auth_api_key_nonce_key FOREIGN KEY (api_key_id) REFERENCES auth_api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
