-- Scoped administrator/agent API keys. Only SHA-256 digests are persisted.
CREATE TABLE IF NOT EXISTS auth_api_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    key_name VARCHAR(80) NOT NULL,
    key_prefix VARCHAR(20) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    scopes JSON NOT NULL,
    ip_allowlist JSON NULL,
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_auth_api_key_hash (key_hash),
    KEY idx_auth_api_key_owner (owner_user_id, created_at),
    KEY idx_auth_api_key_status (revoked_at, expires_at),
    CONSTRAINT fk_auth_api_key_owner FOREIGN KEY (owner_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
