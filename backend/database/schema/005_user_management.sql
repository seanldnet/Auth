-- User and agent directory extensions.
ALTER TABLE auth_users
    ADD COLUMN invite_code VARCHAR(32) NULL AFTER parent_id,
    ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL AFTER invite_code,
    ADD COLUMN assigned_at DATETIME NULL AFTER created_by_user_id,
    ADD UNIQUE KEY uk_auth_users_invite_code (invite_code),
    ADD KEY idx_auth_users_pool (parent_id, role, created_at),
    ADD CONSTRAINT fk_auth_users_created_by FOREIGN KEY (created_by_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT;

