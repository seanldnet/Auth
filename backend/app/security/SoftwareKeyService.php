<?php
declare(strict_types=1);

namespace app\security;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class SoftwareKeyService
{
    /** @return list<array<string,mixed>> */
    public function list(int $softwareId): array
    {
        $this->assertSoftware($softwareId);
        return Db::query(
            'SELECT key_id, fingerprint, status, created_at, revoked_at
             FROM auth_software_public_keys WHERE software_id = ? ORDER BY id DESC',
            [$softwareId],
        );
    }

    /** @return array<string,mixed> */
    public function add(int $softwareId, string $publicKeyPem, int $actorId): array
    {
        $this->assertSoftware($softwareId);
        $publicKeyPem = trim(str_replace("\r\n", "\n", $publicKeyPem));
        if ($publicKeyPem === '' || strlen($publicKeyPem) > 20000) {
            throw new InvalidArgumentException('公钥 PEM 内容为空或超过 20 KB。');
        }
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) throw new InvalidArgumentException('无法解析 RSA 公钥 PEM。');
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || (int)($details['bits'] ?? 0) < 3072) {
            throw new InvalidArgumentException('仅支持 RSA-3072 或更高强度的公钥。');
        }
        $fingerprint = hash('sha256', preg_replace('/\s+/', '', $publicKeyPem) ?? $publicKeyPem);
        $keyId = 'key_' . substr($fingerprint, 0, 16);
        return Db::transaction(function () use ($softwareId, $publicKeyPem, $actorId, $fingerprint, $keyId): array {
            $rows = Db::query('SELECT id FROM auth_software_apps WHERE id = ? AND status <> ? FOR UPDATE', [$softwareId, 'ARCHIVED']);
            if (!isset($rows[0])) throw new RuntimeException('软件不存在或已归档。');
            $active = Db::query(
                "SELECT COUNT(*) AS aggregate FROM auth_software_public_keys WHERE software_id = ? AND status = 'ACTIVE'",
                [$softwareId],
            );
            if ((int)($active[0]['aggregate'] ?? 0) >= 2) {
                throw new RuntimeException('最多允许同时保留 2 把有效公钥；请先撤销旧密钥再添加。');
            }
            Db::execute(
                'INSERT INTO auth_software_public_keys
                 (software_id, key_id, public_key_pem, fingerprint, status, created_by_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$softwareId, $keyId, $publicKeyPem, $fingerprint, 'ACTIVE', $actorId],
            );
            $id = (int)Db::getLastInsID();
            Db::execute(
                'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$actorId, 'SOFTWARE_PUBLIC_KEY_ADDED', 'SOFTWARE', (string)$softwareId,
                 json_encode(['key_id' => $keyId, 'fingerprint' => $fingerprint], JSON_THROW_ON_ERROR)],
            );
            return ['id' => $id, 'key_id' => $keyId, 'fingerprint' => $fingerprint, 'status' => 'ACTIVE'];
        });
    }

    /** @return array<string,mixed> */
    public function revoke(int $softwareId, string $keyId, int $actorId): array
    {
        return Db::transaction(function () use ($softwareId, $keyId, $actorId): array {
            $rows = Db::query(
                'SELECT id, status, fingerprint FROM auth_software_public_keys WHERE software_id = ? AND key_id = ? FOR UPDATE',
                [$softwareId, $keyId],
            );
            $key = $rows[0] ?? null;
            if (!is_array($key)) throw new RuntimeException('签名公钥不存在。');
            if ($key['status'] === 'REVOKED') throw new RuntimeException('该签名公钥已经撤销。');
            Db::execute(
                "UPDATE auth_software_public_keys SET status = 'REVOKED', revoked_at = UTC_TIMESTAMP() WHERE id = ?",
                [(int)$key['id']],
            );
            Db::execute(
                'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$actorId, 'SOFTWARE_PUBLIC_KEY_REVOKED', 'SOFTWARE', (string)$softwareId,
                 json_encode(['key_id' => $keyId, 'fingerprint' => $key['fingerprint']], JSON_THROW_ON_ERROR)],
            );
            return ['key_id' => $keyId, 'status' => 'REVOKED'];
        });
    }

    private function assertSoftware(int $softwareId): void
    {
        if ($softwareId < 1) throw new InvalidArgumentException('软件 ID 无效。');
        $rows = Db::query('SELECT id FROM auth_software_apps WHERE id = ? LIMIT 1', [$softwareId]);
        if (!isset($rows[0])) throw new RuntimeException('软件不存在。');
    }
}
