-- License key replacement history and request idempotency.
CREATE TABLE IF NOT EXISTS auth_license_replacements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    old_license_id BIGINT UNSIGNED NOT NULL,
    new_license_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_license_replacement_old (old_license_id),
    UNIQUE KEY uk_license_replacement_new (new_license_id),
    UNIQUE KEY uk_license_replacement_idempotency (idempotency_key),
    CONSTRAINT fk_license_replacement_old FOREIGN KEY (old_license_id) REFERENCES auth_licenses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_replacement_new FOREIGN KEY (new_license_id) REFERENCES auth_licenses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_license_replacement_actor FOREIGN KEY (actor_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
