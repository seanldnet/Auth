-- Core software catalog and software-scoped license plans.
-- MySQL 8.0+, InnoDB. Existing auth_ table prefix is explicit in SQL.

CREATE TABLE IF NOT EXISTS auth_software_apps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    app_id VARCHAR(32) NOT NULL,
    app_secret_hash CHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(1000) NULL,
    status ENUM('ACTIVE','DISABLED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    renew_consumes_quota TINYINT(1) NOT NULL DEFAULT 0,
    offline_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_software_app_id (app_id),
    KEY idx_software_status_created (status, created_at),
    CONSTRAINT fk_software_creator FOREIGN KEY (created_by) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_license_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    plan_code VARCHAR(32) NOT NULL,
    duration_unit ENUM('DAY','MONTH','YEAR','PERMANENT') NOT NULL,
    duration_value SMALLINT UNSIGNED NULL,
    price DECIMAL(20,8) NOT NULL DEFAULT 0,
    currency ENUM('CNY','USDT') NOT NULL,
    status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
    description VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_plan_software_code (software_id, plan_code),
    KEY idx_plan_software_status (software_id, status),
    CONSTRAINT fk_plan_software FOREIGN KEY (software_id) REFERENCES auth_software_apps(id) ON DELETE RESTRICT,
    CONSTRAINT fk_plan_creator FOREIGN KEY (created_by) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_plan_duration CHECK (
        (duration_unit = 'PERMANENT' AND duration_value IS NULL)
        OR (duration_unit <> 'PERMANENT' AND duration_value IS NOT NULL AND duration_value > 0)
    ),
    CONSTRAINT chk_plan_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
