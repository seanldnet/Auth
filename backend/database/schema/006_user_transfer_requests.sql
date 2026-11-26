-- Auditable user ownership transfer workflow.
CREATE TABLE IF NOT EXISTS auth_user_transfer_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    from_parent_id BIGINT UNSIGNED NULL,
    requested_parent_id BIGINT UNSIGNED NULL,
    requested_by_user_id BIGINT UNSIGNED NOT NULL,
    request_reason VARCHAR(500) NULL,
    status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    review_note VARCHAR(500) NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_transfer_status_created (status, created_at),
    KEY idx_transfer_user_created (user_id, created_at),
    KEY idx_transfer_requester_created (requested_by_user_id, created_at),
    CONSTRAINT fk_transfer_user FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_from_parent FOREIGN KEY (from_parent_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_to_parent FOREIGN KEY (requested_parent_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_requester FOREIGN KEY (requested_by_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
