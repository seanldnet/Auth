<?php
declare(strict_types=1);

namespace app\middleware;

use app\auth\AuthContext;
use app\security\ApiKeyRequestSigner;
use app\security\ApiKeySecretCipher;
use Closure;
use think\Request;
use think\Response;
use think\facade\Db;

final class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $keyId = trim((string)$request->header('x-api-key-id', ''));
        $timestamp = trim((string)$request->header('x-api-timestamp', ''));
        $nonce = trim((string)$request->header('x-api-nonce', ''));
        $signature = trim((string)$request->header('x-api-signature', ''));

        if (!preg_match('/^ak_[a-f0-9]{24}$/', $keyId)
            || !preg_match('/^[0-9]{10}$/', $timestamp)
            || abs(time() - (int)$timestamp) > 300
            || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/i', $signature)
        ) {
            return json(['success' => false, 'message' => 'API 密钥认证失败'], 401);
        }

        $rows = Db::query(
            'SELECT k.id AS api_key_row_id, k.key_id, k.secret_ciphertext, k.permissions, k.ip_allowlist,
                    k.status AS key_status, k.expires_at, u.id AS user_id, u.username, u.role, u.status AS user_status,
                    u.parent_id, u.must_change_password
             FROM auth_api_keys k INNER JOIN auth_users u ON u.id = k.owner_user_id
             WHERE k.key_id = ? LIMIT 1',
            [$keyId],
        );
        $record = $rows[0] ?? null;
        if (!is_array($record) || $record['key_status'] !== 'ACTIVE' || $record['user_status'] !== 'ACTIVE'
            || (int)$record['must_change_password'] === 1
            || ($record['expires_at'] !== null && strtotime((string)$record['expires_at'] . ' UTC') <= time())
        ) {
            return json(['success' => false, 'message' => 'API 密钥认证失败'], 401);
        }

        $allowlist = $record['ip_allowlist'] === null ? [] : (json_decode((string)$record['ip_allowlist'], true) ?: []);
        $clientIp = (string)$request->ip();
        if ($allowlist !== [] && !in_array($clientIp, $allowlist, true)) {
            return json(['success' => false, 'message' => '来源 IP 不在 API 密钥白名单内'], 403);
        }

        try {
            $secret = ApiKeySecretCipher::decrypt((string)$record['secret_ciphertext']);
            $canonical = ApiKeyRequestSigner::canonicalRequest(
                (string)$request->method(), (string)$request->pathinfo(), $timestamp, $nonce, (string)$request->getContent(),
            );
            if (!ApiKeyRequestSigner::verify($secret, $canonical, $signature)) {
                return json(['success' => false, 'message' => 'API 密钥认证失败'], 401);
            }
        } catch (\Throwable) {
            return json(['success' => false, 'message' => 'API 密钥认证失败'], 401);
        }

        try {
            Db::transaction(function () use ($record, $nonce): void {
                Db::execute(
                    'INSERT INTO auth_api_key_nonces (api_key_id, nonce, expires_at, created_at)
                     VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), UTC_TIMESTAMP())',
                    [(int)$record['api_key_row_id'], $nonce],
                );
                Db::execute('UPDATE auth_api_keys SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$record['api_key_row_id']]);
            });
        } catch (\think\exception\DbException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                return json(['success' => false, 'message' => '请求重复，请生成新的 Nonce'], 409);
            }
            return json(['success' => false, 'message' => 'API 密钥认证服务暂不可用'], 503);
        }

        $permissions = json_decode((string)$record['permissions'], true);
        AuthContext::set($request, [
            'id' => (int)$record['user_id'],
            'username' => (string)$record['username'],
            'role' => (string)$record['role'],
            'status' => (string)$record['user_status'],
            'parent_id' => $record['parent_id'] === null ? null : (int)$record['parent_id'],
            'must_change_password' => (int)$record['must_change_password'],
            'auth_type' => 'API_KEY',
            'api_key_id' => (string)$record['key_id'],
            'api_key_permissions' => is_array($permissions) ? $permissions : [],
        ]);

        return $next($request);
    }
}
