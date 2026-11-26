-- Initialize exactly one numeric wallet balance for every existing user.
-- The display unit is a global setting and does not change stored balances.
INSERT IGNORE INTO auth_wallet_accounts (user_id, balance, created_at, updated_at)
SELECT id, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM auth_users;
