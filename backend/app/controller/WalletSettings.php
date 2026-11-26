<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use think\Request;
use think\facade\Db;
use think\response\Json;

final class WalletSettings
{
    public function index(Request $request): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }

        $rows = Db::query(
            "SELECT setting_value FROM auth_system_settings WHERE setting_key = 'wallet_display_unit' LIMIT 1"
        );
        $unit = strtoupper((string) ($rows[0]['setting_value'] ?? 'CNY'));

        return json(['success' => true, 'data' => ['wallet_display_unit' => $unit]]);
    }

    public function save(Request $request): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }

        $payload = RequestPayload::all($request);
        $unit = strtoupper(trim((string) ($payload['wallet_display_unit'] ?? '')));
        if (!in_array($unit, ['CNY', 'U'], true)) {
            return json(['success' => false, 'message' => '显示单位只能设置为人民币或 U。'], 422);
        }

        Db::execute(
            "INSERT INTO auth_system_settings (setting_key, setting_value, updated_at)
             VALUES ('wallet_display_unit', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = UTC_TIMESTAMP()",
            [$unit],
        );

        return json(['success' => true, 'data' => ['wallet_display_unit' => $unit]]);
    }
}
