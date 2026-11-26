-- Convert former per-currency wallets into one numeric balance per user.
-- Existing CNY and USDT numeric balances are added as-is; no FX conversion occurs.
-- Supports both upgraded databases (legacy currency columns) and fresh installs.
DELIMITER //
CREATE PROCEDURE migrate_single_wallet()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'auth_wallet_accounts'
          AND COLUMN_NAME = 'currency'
    ) THEN
        CREATE TEMPORARY TABLE auth_wallet_accounts_merged AS
            SELECT user_id, SUM(balance) AS balance
            FROM auth_wallet_accounts
            GROUP BY user_id;
        DELETE FROM auth_wallet_accounts;
        ALTER TABLE auth_wallet_accounts
            DROP INDEX uk_wallet_user_currency,
            DROP COLUMN currency,
            ADD UNIQUE KEY uk_wallet_user (user_id);
        INSERT INTO auth_wallet_accounts (user_id, balance, created_at, updated_at)
        SELECT user_id, balance, UTC_TIMESTAMP(), UTC_TIMESTAMP()
        FROM auth_wallet_accounts_merged;
        DROP TEMPORARY TABLE auth_wallet_accounts_merged;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'auth_wallet_transactions'
          AND COLUMN_NAME = 'currency'
    ) THEN
        ALTER TABLE auth_wallet_transactions DROP COLUMN currency;
    END IF;

    INSERT IGNORE INTO auth_wallet_accounts (user_id, balance, created_at, updated_at)
    SELECT id, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM auth_users;
END//
DELIMITER ;

CALL migrate_single_wallet();
DROP PROCEDURE migrate_single_wallet;
