-- Platform-signed offline license leases. Private key material is never stored here;
-- signer_key_ref points to an external KMS/HSM signing key.
CREATE TABLE IF NOT EXISTS auth_platform_signing_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_id VARCHAR(32) NOT NULL,
    public_key_pem TEXT NOT NULL,
    signer_key_ref VARCHAR(255) NOT NULL,
    algorithm VARCHAR(32) NOT NULL DEFAULT 'RSA-PSS-SHA256',
    status ENUM('ACTIVE','RETIRED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    verify_until DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_platform_signing_key_id (key_id),
    KEY idx_platform_signing_key_status (status, is_primary),
    CONSTRAINT fk_platform_signing_key_creator FOREIGN KEY (created_by) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_offline_license_leases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lease_id CHAR(36) NOT NULL,
    license_id BIGINT UNSIGNED NOT NULL,
    software_id BIGINT UNSIGNED NOT NULL,
    platform_key_id BIGINT UNSIGNED NOT NULL,
    device_fingerprint_hash CHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    issued_at DATETIME NOT NULL,
    not_before DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_offline_lease_id (lease_id),
    UNIQUE KEY uk_offline_lease_token_hash (token_hash),
    KEY idx_offline_lease_license_expiry (license_id, expires_at),
    KEY idx_offline_lease_software_expiry (software_id, expires_at),
    CONSTRAINT fk_offline_lease_license FOREIGN KEY (license_id) REFERENCES auth_licenses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_offline_lease_software FOREIGN KEY (software_id) REFERENCES auth_software_apps(id) ON DELETE RESTRICT,
    CONSTRAINT fk_offline_lease_platform_key FOREIGN KEY (platform_key_id) REFERENCES auth_platform_signing_keys(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
