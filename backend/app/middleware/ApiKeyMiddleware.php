<?php
declare(strict_types=1);

namespace app\\middleware;

use app\\auth\\AuthContext;
use app\\security\\ApiKeyRequestSigner;
use app\\security\\ApiKeySecretCipher;
use Closure;
use think\\facade\\Db;
use think\\Request;
use think\\Response;

final class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string)$request->header('authorization', '');
        $keyId = trim((string)$request->header('x-api-key-id', ''));
        $timestamp = trim((string)$request->header('x-api-timestamp', ''));
        $nonce = trim((string)$request->header('x-api-nonce', ''));
        $signature = trim((string)$request->header('x-api-signature', ''));
        $signedHeadersPresent = $keyId !== '' || $timestamp !== '' || $nonce !== '' || $signature !== '';
        $record = null;

        if ($signedHeadersPresent) {
            if (!preg_match('/^ak_[a-f0-9]{12}$/i', $keyId)
                || !preg_match('/^[0-9]{10}$/', $timestamp)
                || abs(time() - (int)$timestamp) > 300
                || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)
                || !preg_match('/^[a-f0-9]{64}$/i', $signature)
            ) {
                return json(['success' => false, 'message' => 'API Key 签名认证失败'], 401);
            }
            $rows = Db::query(
                'SELECT k.id AS api_key_id, k.owner_user_id, k.key_prefix, k.secret_ciphertext,
                        k.scopes, k.ip_allowlist, k.expires_at, k.revoked_at,
                        u.username, u.role, u.status, u.must_change_password
                 FROM auth_api_keys k INNER JOIN auth_users u ON u.id = k.owner_user_id
                 WHERE k.key_prefix = ? LIMIT 1',
                [$keyId],
            );
            $record = $rows[0] ?? null;
            if (!is_array($record) || !is_string($record['secret_ciphertext'] ?? null)
                || $record['secret_ciphertext'] === '') {
                return json(['success' => false, 'message' => 'API Key 不存在或尚不支持签名认证'], 401);
            }
            try {
                $secret = ApiKeySecretCipher::decrypt((string)$record['secret_ciphertext']);
                $canonical = ApiKeyRequestSigner::canonicalRequest(
                    (string)$request->method(), (string)$request->pathinfo(), $timestamp, $nonce,
                    (string)$request->getContent(),
                );
                if (!ApiKeyRequestSigner::verify($secret, $canonical, $signature)) {
                    return json(['success' => false, 'message' => 'API Key 签名认证失败'], 401);
                }
            } catch (\\Throwable) {
                return json(['success' => false, 'message' => 'API Key 签名认证失败'], 401);
            }
        } else {
            if (!preg_match('/^Bearer\\s+(ak_[a-f0-9]{64})$/i', $authorization, $matches)) {
                return json(['success' => false, 'message' => '缺少有效的 API Key'], 401);
            }
            $plain = strtolower($matches[1]);
            $rows = Db::query(
                'SELECT k.id AS api_key_id, k.owner_user_id, k.key_prefix, k.secret_ciphertext,
                        k.scopes, k.ip_allowlist, k.expires_at, k.revoked_at,
                        u.username, u.role, u.status, u.must_change_password
                 FROM auth_api_keys k INNER JOIN auth_users u ON u.id = k.owner_user_id
                 WHERE k.key_hash = ? LIMIT 1',
                [hash('sha256', $plain)],
            );
            $record = $rows[0] ?? null;
        }

        if (!is_array($record) || $record['revoked_at'] !== null
            || ($record['expires_at'] !== null && strtotime((string)$record['expires_at'] . ' UTC') <= time())
            || $record['status'] !== 'ACTIVE'
            || (int)($record['must_change_password'] ?? 0) === 1
        ) {
            return json(['success' => false, 'message' => 'API Key 无效、已撤销、已过期或账号不可用'], 401);
        }

        $ips = $record['ip_allowlist'] === null ? [] : json_decode((string)$record['ip_allowlist'], true);
        if (is_array($ips) && $ips !== [] && !in_array((string)$request->ip(), $ips, true)) {
            return json(['success' => false, 'message' => '当前 IP 不在 API Key 白名单内'], 403);
        }

        $scopes = json_decode((string)$record['scopes'], true);
        $scopes = is_array($scopes) ? $scopes : [];
        $path = trim((string)$request->pathinfo(), '/');
        $method = strtoupper((string)$request->method());
        $isAdminFullScope = ($record['role'] ?? '') === 'ADMIN'
            && in_array('*', $scopes, true)
            && str_starts_with($path, 'api/')
            && !str_starts_with($path, 'api/auth/');
        $required = $isAdminFullScope ? '*' : match ([$path, $method]) {
            ['api/licenses', 'GET'], ['api/v1/licenses', 'GET'] => 'licenses.read',
            ['api/licenses', 'POST'], ['api/v1/licenses', 'POST'] => 'licenses.create',
            ['api/users', 'GET'], ['api/v1/users', 'GET'] => 'users.read',
            ['api/v1/users/children', 'GET'] => 'users.children.read',
            ['api/license-quotas', 'GET'], ['api/v1/quotas', 'GET'] => 'quotas.read',
            default => null,
        };
        if ($required === null && (preg_match('#^api/licenses/[0-9]+/renew$#', $path)
            || preg_match('#^api/v1/licenses/[0-9]+/renew$#', $path))) {
            $required = 'licenses.renew';
        }
        if ($required === null && (preg_match('#^api/licenses/[0-9]+/replace$#', $path)
            || preg_match('#^api/v1/licenses/[0-9]+/replace$#', $path))) {
            $required = 'licenses.replace';
        }
        if ($required === null && preg_match('#^api/license-quotas/[0-9]+/transactions$#', $path)) {
            $required = 'quotas.read';
        }
        if ($required === null || (!in_array('*', $scopes, true) && !in_array($required, $scopes, true))) {
            return json(['success' => false, 'message' => 'API Key 未授权此接口或请求方法'], 403);
        }

        try {
            Db::transaction(function () use ($record, $signedHeadersPresent, $nonce): void {
                if ($signedHeadersPresent) {
                    Db::execute(
                        'INSERT INTO auth_api_key_nonces (api_key_id, nonce, expires_at, created_at)
                         VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), UTC_TIMESTAMP())',
                        [(int)$record['api_key_id'], $nonce],
                    );
                }
                Db::execute('UPDATE auth_api_keys SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$record['api_key_id']]);
            });
        } catch (\\Throwable $exception) {
            if ($signedHeadersPresent && str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                return json(['success' => false, 'message' => '请求重复，请使用新的 Nonce'], 409);
            }
            return json(['success' => false, 'message' => 'API Key 认证服务暂不可用'], 503);
        }

        AuthContext::set($request, [
            'id' => (int)$record['owner_user_id'],
            'username' => (string)$record['username'],
            'role' => (string)$record['role'],
            'api_key_id' => (int)$record['api_key_id'],
            'auth_type' => 'API_KEY',
        ]);
        return $next($request);
    }
}
