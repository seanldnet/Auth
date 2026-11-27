<?php
declare(strict_types=1);

namespace app\security;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class ApiKeyService
{
    private const AGENT_SCOPES = ['licenses.create', 'licenses.read', 'licenses.renew', 'licenses.replace', 'users.read', 'users.children.read', 'quotas.read'];

    /** @return array<string,mixed> */
    public function create(int $ownerId, string $name, array $requestedScopes, array $ipAllowlist, ?string $expiresAt): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 80) throw new InvalidArgumentException('API Key 名称不能为空且不能超过 80 字。');
        $ownerRows = Db::query('SELECT id, role, status, must_change_password FROM auth_users WHERE id = ? LIMIT 1', [$ownerId]);
        $owner = $ownerRows[0] ?? null;
        if (!is_array($owner) || $owner['status'] !== 'ACTIVE' || (int)($owner['must_change_password'] ?? 0) === 1 || !in_array($owner['role'], ['ADMIN','AGENT','SUB_AGENT'], true)) {
            throw new RuntimeException('只有启用状态的管理员、代理或下级代理可以创建 API Key。');
        }

        if ($owner['role'] === 'ADMIN') {
            $scopes = ['*'];
        } else {
            $scopes = array_values(array_unique(array_map('strval', $requestedScopes)));
            if ($scopes === [] || array_diff($scopes, self::AGENT_SCOPES) !== []) {
                throw new InvalidArgumentException('代理 API Key 只能使用授权创建/查询、用户/下级查询和额度查询权限。');
            }
        }

        $ips = [];
        foreach ($ipAllowlist as $ip) {
            $ip = trim((string)$ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException('IP 白名单包含无效地址。');
            }
            if ($ip !== '') $ips[] = $ip;
        }
        $ips = array_values(array_unique($ips));
        if (count($ips) > 100) throw new InvalidArgumentException('IP 白名单最多 100 条。');

        $expiry = null;
        if ($expiresAt !== null && trim($expiresAt) !== '') {
            try { $expiry = (new DateTimeImmutable($expiresAt))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
            catch (\Throwable) { throw new InvalidArgumentException('API Key 到期时间格式无效。'); }
            if (strtotime($expiry . ' UTC') <= time()) throw new InvalidArgumentException('API Key 到期时间必须晚于当前时间。');
        }

        $plain = 'ak_' . bin2hex(random_bytes(32));
        $prefix = substr($plain, 0, 15);
        $secretCiphertext = ApiKeySecretCipher::encrypt(substr($plain, 15));
        $id = Db::transaction(function () use ($ownerId, $name, $prefix, $plain, $secretCiphertext, $scopes, $ips, $expiry): int {
            Db::execute(
                'INSERT INTO auth_api_keys
                 (owner_user_id, key_name, key_prefix, key_hash, scopes, ip_allowlist, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$ownerId, $name, $prefix, hash('sha256', $plain), $secretCiphertext,
                 json_encode($scopes, JSON_THROW_ON_ERROR), $ips === [] ? null : json_encode($ips, JSON_THROW_ON_ERROR), $expiry],
            );
            $id = (int)Db::getLastInsID();
            Db::execute(
                'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$ownerId, 'API_KEY_CREATED', 'API_KEY', (string)$id,
                 json_encode(['name' => $name, 'scopes' => $scopes, 'ip_allowlist_count' => count($ips)], JSON_THROW_ON_ERROR)],
            );
            return $id;
        });
        return ['id' => $id, 'name' => $name, 'key_prefix' => $prefix, 'scopes' => $scopes, 'ip_allowlist' => $ips, 'expires_at' => $expiry, 'api_key' => $plain];
    }

    /** @return list<array<string,mixed>> */
    public function list(array $actor): array
    {
        $admin = ($actor['role'] ?? '') === 'ADMIN';
        $where = $admin ? '' : ' WHERE k.owner_user_id = ?';
        $params = $admin ? [] : [(int)$actor['id']];
        return Db::query(
            "SELECT k.id, k.owner_user_id, u.username AS owner_username, k.key_name, k.key_prefix,
                    k.scopes, k.ip_allowlist, k.expires_at, k.last_used_at, k.revoked_at, k.created_at
             FROM auth_api_keys k INNER JOIN auth_users u ON u.id = k.owner_user_id
             {$where} ORDER BY k.id DESC",
            $params,
        );
    }

    public function revoke(array $actor, int $keyId): void
    {
        if ($keyId < 1) throw new InvalidArgumentException('API Key ID 无效。');
        $admin = ($actor['role'] ?? '') === 'ADMIN';
        $where = $admin ? 'id = ?' : 'id = ? AND owner_user_id = ?';
        $params = $admin ? [$keyId] : [$keyId, (int)$actor['id']];
        Db::transaction(function () use ($where, $params, $keyId, $actor): void {
            $rows = Db::query("SELECT id, revoked_at FROM auth_api_keys WHERE {$where} LIMIT 1 FOR UPDATE", $params);
            $key = $rows[0] ?? null;
            if (!is_array($key)) throw new RuntimeException('API Key 不存在或无权操作。');
            if ($key['revoked_at'] !== null) throw new RuntimeException('该 API Key 已撤销。');
            Db::execute('UPDATE auth_api_keys SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$keyId]);
            Db::execute(
                'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, created_at)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                [(int)$actor['id'], 'API_KEY_REVOKED', 'API_KEY', (string)$keyId],
            );
        });
    }
}
