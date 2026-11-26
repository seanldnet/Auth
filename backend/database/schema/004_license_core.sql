-- License issuance and lifecycle audit.
-- Plain license keys are never stored; only SHA-256 digests are persisted.

CREATE TABLE IF NOT EXISTS auth_licenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_no VARCHAR(40) NOT NULL,
    license_key_hash CHAR(64) NOT NULL,
    license_key_hint VARCHAR(24) NOT NULL,
    software_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    issued_by_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('GENERATED','ACTIVE','FROZEN','VOID','EXPIRED') NOT NULL DEFAULT 'GENERATED',
    frozen_from_status ENUM('GENERATED','ACTIVE') NULL,
    device_fingerprint_hash CHAR(64) NULL,
    device_label VARCHAR(120) NULL,
    activated_at DATETIME NULL,
    expires_at DATETIME NULL,
    machine_change_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_license_no (license_no),
    UNIQUE KEY uk_license_key_hash (license_key_hash),
    KEY idx_license_software_status (software_id, status, created_at),
    KEY idx_license_plan (plan_id, status),
    KEY idx_license_owner (owner_user_id, created_at),
    KEY idx_license_device (software_id, device_fingerprint_hash),
    CONSTRAINT fk_license_software FOREIGN KEY (software_id) REFERENCES auth_software_apps(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_plan FOREIGN KEY (plan_id) REFERENCES auth_license_plans(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_owner FOREIGN KEY (owner_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_issuer FOREIGN KEY (issued_by_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_license_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(40) NOT NULL,
    before_state JSON NULL,
    after_state JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_license_event_license_time (license_id, created_at),
    KEY idx_license_event_actor_time (actor_user_id, created_at),
    CONSTRAINT fk_license_event_license FOREIGN KEY (license_id) REFERENCES auth_licenses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_event_actor FOREIGN KEY (actor_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
