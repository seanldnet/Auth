-- Backfill isolated platform wallet accounts for users created before wallet bootstrap.
-- CNY and USDT remain independent; this migration performs no conversion.
INSERT IGNORE INTO auth_wallet_accounts (user_id, currency, balance, created_at, updated_at)
SELECT id, 'CNY', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM auth_users;

INSERT IGNORE INTO auth_wallet_accounts (user_id, currency, balance, created_at, updated_at)
SELECT id, 'USDT', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM auth_users;
