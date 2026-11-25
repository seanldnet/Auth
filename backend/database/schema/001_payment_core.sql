-- MySQL 8.0+ payment core. Apply once per regional database.
-- Platform CNY and USDT wallets remain isolated; no FX/rate fields are stored.

CREATE TABLE IF NOT EXISTS auth_payment_channels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel_code VARCHAR(32) NOT NULL,
    display_name VARCHAR(80) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    allowed_currencies JSON NOT NULL,
    merchant_pid VARCHAR(128) NOT NULL,
    credentials_ciphertext TEXT NOT NULL,
    configuration JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payment_channel_code (channel_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_payment_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_no VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    checkout_token_hash CHAR(64) NOT NULL,
    channel_code VARCHAR(32) NOT NULL,
    currency ENUM('CNY','USDT') NOT NULL,
    amount DECIMAL(26,8) NOT NULL,
    provider_currency VARCHAR(12) NOT NULL,
    provider_amount DECIMAL(26,8) NOT NULL,
    provider_trade_id VARCHAR(128) NULL,
    payment_type VARCHAR(32) NULL,
    provider_token VARCHAR(20) NULL,
    provider_network VARCHAR(32) NULL,
    cashier_url VARCHAR(2048) NULL,
    status ENUM('CREATING','PENDING','PAID','EXPIRED','CANCELLED','REVIEW') NOT NULL DEFAULT 'CREATING',
    expires_at DATETIME NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payment_order_no (order_no),
    UNIQUE KEY uk_payment_idempotency (user_id, idempotency_key),
    UNIQUE KEY uk_payment_provider_trade (channel_code, provider_trade_id),
    KEY idx_payment_user_created (user_id, created_at),
    KEY idx_payment_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_wallet_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    currency ENUM('CNY','USDT') NOT NULL,
    balance DECIMAL(26,8) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_wallet_user_currency (user_id, currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_wallet_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    currency ENUM('CNY','USDT') NOT NULL,
    direction ENUM('CREDIT','DEBIT','REVERSAL') NOT NULL,
    amount DECIMAL(26,8) NOT NULL,
    balance_before DECIMAL(26,8) NOT NULL,
    balance_after DECIMAL(26,8) NOT NULL,
    business_type VARCHAR(32) NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(160) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_wallet_idempotency (idempotency_key),
    KEY idx_wallet_user_created (user_id, created_at),
    KEY idx_wallet_business (business_type, business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_payment_callback_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel_code VARCHAR(32) NOT NULL,
    event_key CHAR(64) NOT NULL,
    order_no VARCHAR(64) NOT NULL,
    provider_trade_id VARCHAR(128) NULL,
    payload_sha256 CHAR(64) NOT NULL,
    processing_status ENUM('PROCESSED','DUPLICATE','REJECTED') NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payment_callback_event (channel_code, event_key),
    KEY idx_callback_order (order_no, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
