<?php
declare(strict_types=1);

namespace app\middleware;

use app\auth\AuthContext;
use Closure;
use think\facade\Db;
use think\Request;
use think\Response;

final class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string)$request->header('authorization', '');
        if (!preg_match('/^Bearer\\s+(ak_[a-f0-9]{64})$/i', $authorization, $matches)) {
            return json(['success' => false, 'message' => '缺少有效的 API Key'], 401);
        }
        $plain = strtolower($matches[1]);
        $rows = Db::query(
            'SELECT k.id AS api_key_id, k.owner_user_id, k.scopes, k.ip_allowlist, k.expires_at,
                    u.username, u.role, u.status
             FROM auth_api_keys k
             INNER JOIN auth_users u ON u.id = k.owner_user_id
             WHERE k.key_hash = ? AND k.revoked_at IS NULL
               AND (k.expires_at IS NULL OR k.expires_at > UTC_TIMESTAMP())
             LIMIT 1',
            [hash('sha256', $plain)],
        );
        $record = $rows[0] ?? null;
        if (!is_array($record) || $record['status'] !== 'ACTIVE') {
            return json(['success' => false, 'message' => 'API Key 无效、已撤销或已过期'], 401);
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
        if ($required === null || (!in_array('*', $scopes, true) && !in_array($required, $scopes, true))) {
            return json(['success' => false, 'message' => 'API Key 未授权此接口或请求方法'], 403);
        }

        Db::execute('UPDATE auth_api_keys SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$record['api_key_id']]);
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
