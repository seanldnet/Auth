-- Agent license quota accounts and immutable allocation/consumption ledger.
CREATE TABLE IF NOT EXISTS auth_agent_license_quotas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    software_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    available_quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    allocated_quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    consumed_quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_agent_quota_scope (user_id, software_id, plan_id),
    KEY idx_agent_quota_software (software_id, plan_id),
    CONSTRAINT fk_agent_quota_user FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_quota_software FOREIGN KEY (software_id) REFERENCES auth_software_apps(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_quota_plan FOREIGN KEY (plan_id) REFERENCES auth_license_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_agent_license_quota_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quota_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    software_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('ALLOCATE','CONSUME','REVERSAL') NOT NULL,
    quantity BIGINT UNSIGNED NOT NULL,
    available_before BIGINT UNSIGNED NOT NULL,
    available_after BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    business_type VARCHAR(32) NOT NULL,
    business_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(160) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_agent_quota_idempotency (idempotency_key),
    KEY idx_agent_quota_ledger_user (user_id, created_at),
    KEY idx_agent_quota_ledger_scope (software_id, plan_id, created_at),
    CONSTRAINT fk_agent_quota_tx_quota FOREIGN KEY (quota_id) REFERENCES auth_agent_license_quotas(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_quota_tx_user FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_quota_tx_actor FOREIGN KEY (actor_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
