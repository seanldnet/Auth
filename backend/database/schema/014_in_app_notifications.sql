-- In-app notifications with deterministic event keys for idempotent scheduled delivery.
CREATE TABLE IF NOT EXISTS auth_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(190) NOT NULL,
    category VARCHAR(40) NOT NULL,
    title VARCHAR(120) NOT NULL,
    body VARCHAR(500) NOT NULL,
    payload JSON NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_auth_notification_event (user_id, event_key),
    KEY idx_auth_notification_user_read_time (user_id, read_at, created_at),
    CONSTRAINT fk_auth_notification_user FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
