-- Idempotent license renewal history.
CREATE TABLE IF NOT EXISTS auth_license_renewals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    previous_expires_at DATETIME NULL,
    new_expires_at DATETIME NULL,
    quota_consumed TINYINT(1) NOT NULL DEFAULT 0,
    idempotency_key VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_license_renewal_idempotency (idempotency_key),
    KEY idx_license_renewal_license_time (license_id, created_at),
    CONSTRAINT fk_license_renewal_license FOREIGN KEY (license_id) REFERENCES auth_licenses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_renewal_actor FOREIGN KEY (actor_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_renewal_plan FOREIGN KEY (plan_id) REFERENCES auth_license_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
