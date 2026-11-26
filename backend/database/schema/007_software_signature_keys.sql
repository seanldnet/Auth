-- Software client public keys and anti-replay request nonces.
CREATE TABLE IF NOT EXISTS auth_software_public_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    key_id VARCHAR(24) NOT NULL,
    public_key_pem MEDIUMTEXT NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    status ENUM('ACTIVE','REVOKED') NOT NULL DEFAULT 'ACTIVE',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_software_key_id (software_id, key_id),
    KEY idx_software_key_status (software_id, status),
    CONSTRAINT fk_software_key_app FOREIGN KEY (software_id) REFERENCES auth_software_apps(id) ON DELETE RESTRICT,
    CONSTRAINT fk_software_key_creator FOREIGN KEY (created_by_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_software_request_nonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    app_id VARCHAR(32) NOT NULL,
    key_id VARCHAR(24) NOT NULL,
    nonce VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_software_nonce (app_id, key_id, nonce),
    KEY idx_software_nonce_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
