-- Region node registry. This is control-plane metadata only; it does not imply cross-region replication is active.
CREATE TABLE IF NOT EXISTS auth_region_nodes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    region_code VARCHAR(32) NOT NULL,
    region_name VARCHAR(80) NOT NULL,
    api_base_url VARCHAR(500) NOT NULL,
    traffic_weight SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'DISABLED',
    description VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_auth_region_code (region_code),
    KEY idx_auth_region_status_weight (status, traffic_weight)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
