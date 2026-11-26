<?php
declare(strict_types=1);

namespace app\payment;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class WalletAdminService
{
    /** @return array{items:list<array<string,mixed>>,total:int,page:int,page_size:int,display_unit:string} */
    public function listUsers(int $page = 1, int $pageSize = 20, string $keyword = '', string $role = ''): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $where = [];
        $params = [];
        if ($keyword !== '') {
            $where[] = 'u.username LIKE ?';
            $params[] = '%' . mb_substr($keyword, 0, 80) . '%';
        }
        if ($role !== '') {
            if (!in_array($role, ['ADMIN','AGENT','SUB_AGENT','USER'], true)) throw new InvalidArgumentException('用户角色筛选无效。');
            $where[] = 'u.role = ?';
            $params[] = $role;
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = Db::query("SELECT COUNT(*) AS aggregate FROM auth_users u{$clause}", $params);
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT u.id, u.username, u.role, u.status, COALESCE(w.balance, 0) AS balance
             FROM auth_users u LEFT JOIN auth_wallet_accounts w ON w.user_id = u.id
             {$clause} ORDER BY u.id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        $settings = Db::query("SELECT setting_value FROM auth_system_settings WHERE setting_key = 'wallet_display_unit' LIMIT 1");
        return [
            'items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize,
            'display_unit' => strtoupper((string)($settings[0]['setting_value'] ?? 'CNY')),
        ];
    }

    /** @return array<string,mixed> */
    public function credit(int $userId, string $amount, string $idempotencyKey, string $description, int $actorId): array
    {
        if ($userId < 1) throw new InvalidArgumentException('用户 ID 无效。');
        $amount = trim($amount);
        if (!preg_match('/^(?:0|[1-9]\d{0,17})(?:\.\d{1,8})?$/', $amount) || preg_match('/^0(?:\.0+)?$/', $amount)) {
            throw new InvalidArgumentException('充值金额必须大于 0，最多保留 8 位小数。');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('请提供有效的幂等键。');
        }
        $description = mb_substr(trim($description), 0, 255, 'UTF-8');
        if ($description === '') throw new InvalidArgumentException('请填写手动充值备注。');

        return Db::transaction(function () use ($userId, $amount, $idempotencyKey, $description, $actorId): array {
            $duplicate = Db::query('SELECT id FROM auth_wallet_transactions WHERE idempotency_key = ? LIMIT 1', [$idempotencyKey]);
            if ($duplicate !== []) throw new RuntimeException('该充值请求已处理，请勿重复提交。');
            $user = Db::query('SELECT id, username FROM auth_users WHERE id = ? LIMIT 1 FOR UPDATE', [$userId]);
            if (!isset($user[0])) throw new RuntimeException('目标用户不存在。');
            Db::execute(
                'INSERT INTO auth_wallet_accounts (user_id, balance, created_at, updated_at)
                 VALUES (?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)',
                [$userId],
            );
            $walletRows = Db::query('SELECT id, balance FROM auth_wallet_accounts WHERE user_id = ? LIMIT 1 FOR UPDATE', [$userId]);
            $wallet = $walletRows[0] ?? null;
            if (!is_array($wallet)) throw new RuntimeException('钱包账户初始化失败。');
            $before = (string)$wallet['balance'];
            Db::execute(
                'UPDATE auth_wallet_accounts SET balance = balance + ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$amount, (int)$wallet['id']],
            );
            $balanceRows = Db::query('SELECT balance FROM auth_wallet_accounts WHERE id = ? LIMIT 1', [(int)$wallet['id']]);
            $after = (string)($balanceRows[0]['balance'] ?? '');
            if ($after === '') throw new RuntimeException('无法读取充值后的余额。');
            Db::execute(
                'INSERT INTO auth_wallet_transactions
                 (user_id, direction, amount, balance_before, balance_after, business_type, business_id,
                  idempotency_key, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$userId, 'CREDIT', $amount, $before, $after, 'ADMIN_MANUAL_CREDIT', $userId, $idempotencyKey, $description],
            );
            Db::execute(
                'INSERT INTO auth_audit_logs
                 (actor_user_id, event_type, target_type, target_id, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$actorId, 'WALLET_MANUAL_CREDIT', 'USER', (string)$userId,
                 json_encode(['amount' => $amount, 'balance_before' => $before, 'balance_after' => $after, 'description' => $description], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
            );
            $settings = Db::query("SELECT setting_value FROM auth_system_settings WHERE setting_key = 'wallet_display_unit' LIMIT 1");
            return [
                'user_id' => $userId, 'username' => (string)$user[0]['username'],
                'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after,
                'display_unit' => strtoupper((string)($settings[0]['setting_value'] ?? 'CNY')),
            ];
        });
    }
}
