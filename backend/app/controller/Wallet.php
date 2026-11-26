<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use think\Request;
use think\facade\Db;
use think\response\Json;

final class Wallet
{
    public function index(Request $request): Json
    {
        $user = AuthContext::get($request);
        if ($user === null) return json(['success' => false, 'message' => '请先登录'], 401);
        $userId = (int)$user['id'];
        Db::execute(
            'INSERT INTO auth_wallet_accounts (user_id, balance, created_at, updated_at)
             VALUES (?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)',
            [$userId],
        );
        $rows = Db::query('SELECT balance, updated_at FROM auth_wallet_accounts WHERE user_id = ? LIMIT 1', [$userId]);
        $settings = Db::query("SELECT setting_value FROM auth_system_settings WHERE setting_key = 'wallet_display_unit' LIMIT 1");
        $transactions = Db::query(
            'SELECT id, direction, amount, balance_before, balance_after, business_type, description, created_at
             FROM auth_wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 50',
            [$userId],
        );
        return json(['success' => true, 'data' => [
            'balance' => (string)($rows[0]['balance'] ?? '0'),
            'updated_at' => $rows[0]['updated_at'] ?? null,
            'display_unit' => strtoupper((string)($settings[0]['setting_value'] ?? 'CNY')),
            'transactions' => $transactions,
        ]]);
    }
}
