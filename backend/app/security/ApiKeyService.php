<?php
declare(strict_types=1);

namespace app\security;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class ApiKeyService
{
    private const AGENT_PERMISSIONS = [
        'license.create', 'license.replace', 'license.read', 'user.read', 'sub_agent.read',
    ];

    /** @return array<string,mixed> */
    public function create(array $actor, string $name, array $permissions, array $ipAllowlist, string $expiresAt, string $ip, string $userAgent): array
    {
        $role = (string)($actor['role'] ?? '');
        if (!in_array($role, ['ADMIN', 'AGENT', 'SUB_AGENT'], true)) {
            throw new RuntimeException('当前账号无权创建 API 密钥。');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) throw new InvalidArgumentException('密钥名称必须为 1–80 个字符。');

        $allowed = match ($role) {
            'ADMIN' => ['*', ...self::AGENT_PERMISSIONS],
            'AGENT' => self::AGENT_PERMISSIONS,
            'SUB_AGENT' => array_values(array_diff(self::AGENT_PERMISSIONS, ['sub_agent.read'])),
            default => [],
        };
        $permissions = array_values(array_unique(array_map('strval', $permissions)));
        if ($permissions === []) $permissions = $role === 'ADMIN' ? ['*'] : self::AGENT_PERMISSIONS;
        foreach ($permissions as $permission) {
            if (!in_array($permission, $allowed, true)) throw new InvalidArgumentException('API 密钥权限包含当前角色不允许的操作。');
        }
        if (in_array('*', $permissions, true) && count($permissions) !== 1) {
            throw new InvalidArgumentException('全站权限不能与单项权限同时配置。');
        }

        $normalizedIps = [];
        foreach ($ipAllowlist as $address) {
            $address = trim((string)$address);
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException('IP 白名单仅支持完整 IPv4/IPv6 地址。');
            }
            $normalizedIps[] = $address;
        }
        $normalizedIps = array_values(array_unique($normalizedIps));
        if (count($normalizedIps) > 100) throw new InvalidArgumentException('单个 API 密钥最多配置 100 个 IP。');

        $expiry = null;
        if ($expiresAt !== '') {
            try {
                $date = new DateTimeImmutable($expiresAt);
                $date = $date->setTimezone(new DateTimeZone('UTC'));
            } catch (\Throwable) {
                throw new InvalidArgumentException('过期时间格式无效。');
            }
            if ($date->getTimestamp() <= time()) throw new InvalidArgumentException('过期时间必须晚于当前时间。');
            $expiry = $date->format('Y-m-d H:i:s');
        }

        $keyId = 'ak_' . bin2hex(random_bytes(12));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $ciphertext = ApiKeySecretCipher::encrypt($secret);
        $id = Db::transaction(function () use ($actor, $name, $permissions, $normalizedIps, $expiry, $keyId, $ciphertext, $ip, $userAgent): int {
            Db::execute(
                'INSERT INTO auth_api_keys
                 (key_id, owner_user_id, name, secret_ciphertext, permissions, ip_allowlist, status, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, \'ACTIVE\', ?, UTC_TIMESTAMP())',
                [$keyId, (int)$actor['id'], $name, $ciphertext,
                 json_encode($permissions, JSON_THROW_ON_ERROR), $normalizedIps === [] ? null : json_encode($normalizedIps, JSON_THROW_ON_ERROR), $expiry],
            );
            $id = (int)Db::getLastInsID();
            $this->audit((int)$actor['id'], 'API_KEY_CREATED', $keyId, $ip, $userAgent, [
                'name' => $name, 'permissions' => $permissions, 'ip_allowlist_count' => count($normalizedIps),
            ]);
            return $id;
        });

        return [
            'id' => $id, 'key_id' => $keyId, 'api_key' => $keyId . '.' . $secret,
            'name' => $name, 'permissions' => $permissions, 'ip_allowlist' => $normalizedIps,
            'expires_at' => $expiry, 'status' => 'ACTIVE',
            'warning' => '完整 API Key 仅显示本次，请立即保存；之后无法再次查看。',
        ];
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function list(array $actor, int $page = 1, int $pageSize = 20): array
    {
        $role = (string)($actor['role'] ?? '');
        if (!in_array($role, ['ADMIN', 'AGENT', 'SUB_AGENT'], true)) {
            throw new RuntimeException('当前账号无权查看 API 密钥。');
        }
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $where = [];
        $params = [];
        if ($role !== 'ADMIN') {
            $where[] = 'k.owner_user_id = ?';
            $params[] = (int)$actor['id'];
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = Db::query('SELECT COUNT(*) AS aggregate FROM auth_api_keys k' . $clause, $params);
        $items = Db::query(
            'SELECT k.id, k.key_id, k.owner_user_id, u.username AS owner_username, k.name,
                    k.permissions, k.ip_allowlist, k.status, k.expires_at, k.last_used_at, k.created_at, k.revoked_at
             FROM auth_api_keys k INNER JOIN auth_users u ON u.id = k.owner_user_id'
             . $clause . ' ORDER BY k.id DESC LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize),
            $params,
        );
        foreach ($items as &$item) {
            $item['permissions'] = json_decode((string)$item['permissions'], true) ?: [];
            $item['ip_allowlist'] = $item['ip_allowlist'] === null ? [] : (json_decode((string)$item['ip_allowlist'], true) ?: []);
        }
        unset($item);
        return ['items' => $items, 'total' => (int)($count[0]['aggregate'] ?? 0)];
    }

    /** @return array<string,mixed> */
    public function revoke(array $actor, string $keyId, string $ip, string $userAgent): array
    {
        if (!in_array((string)($actor['role'] ?? ''), ['ADMIN', 'AGENT', 'SUB_AGENT'], true)) {
            throw new RuntimeException('当前账号无权撤销 API 密钥。');
        }
        return Db::transaction(function () use ($actor, $keyId, $ip, $userAgent): array {
            $rows = Db::query('SELECT id, owner_user_id, status FROM auth_api_keys WHERE key_id = ? FOR UPDATE', [$keyId]);
            $key = $rows[0] ?? null;
            if (!is_array($key)) throw new RuntimeException('API 密钥不存在。');
            if (($actor['role'] ?? '') !== 'ADMIN' && (int)$key['owner_user_id'] !== (int)$actor['id']) {
                throw new RuntimeException('无权操作其他账号的 API 密钥。');
            }
            if ($key['status'] === 'REVOKED') throw new RuntimeException('API 密钥已经撤销。');
            Db::execute("UPDATE auth_api_keys SET status = 'REVOKED', revoked_at = UTC_TIMESTAMP() WHERE id = ?", [(int)$key['id']]);
            $this->audit((int)$actor['id'], 'API_KEY_REVOKED', $keyId, $ip, $userAgent, []);
            return ['key_id' => $keyId, 'status' => 'REVOKED'];
        });
    }

    /** @param array<string,mixed> $metadata */
    private function audit(int $actorId, string $event, string $keyId, string $ip, string $userAgent, array $metadata): void
    {
        Db::execute(
            'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, ip_address, user_agent, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$actorId, $event, 'API_KEY', $keyId, $ip ?: null, mb_substr($userAgent, 0, 255, 'UTF-8'),
             json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
    }
}
