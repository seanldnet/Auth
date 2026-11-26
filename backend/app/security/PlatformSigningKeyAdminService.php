<?php
declare(strict_types=1);

namespace app\security;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;
use think\facade\Db;

final class PlatformSigningKeyAdminService
{
    /** @return array<int,array<string,mixed>> */
    public function index(): array
    {
        return Db::query(
            'SELECT id, key_id, algorithm, status, is_primary, verify_until, created_at, updated_at
             FROM auth_platform_signing_keys ORDER BY is_primary DESC, id DESC',
        );
    }

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    public function create(array $input, int $actorId): array
    {
        $keyId = strtolower(trim((string)($input['key_id'] ?? '')));
        $publicKeyPem = trim((string)($input['public_key_pem'] ?? ''));
        $keyReference = trim((string)($input['signer_key_ref'] ?? ''));
        $isPrimary = filter_var($input['is_primary'] ?? false, FILTER_VALIDATE_BOOL);
        if (!preg_match('/^pkey_[a-f0-9]{16}$/', $keyId)) {
            throw new InvalidArgumentException('平台 Key ID 必须为 pkey_ 加 16 位小写十六进制字符。');
        }
        if ($publicKeyPem === '' || strlen($publicKeyPem) > 20000
            || (!str_contains($publicKeyPem, '-----BEGIN PUBLIC KEY-----')
                && !str_contains($publicKeyPem, '-----BEGIN RSA PUBLIC KEY-----'))) {
            throw new InvalidArgumentException('平台 RSA 公钥 PEM 不能为空且不能超过 20 KB。');
        }
        if ($keyReference === '' || strlen($keyReference) > 255
            || !preg_match('/^[A-Za-z0-9._:/-]+$/', $keyReference)) {
            throw new InvalidArgumentException('KMS/HSM Key Reference 格式无效。');
        }
        try {
            $publicKey = PublicKeyLoader::load($publicKeyPem);
            $publicKey = $publicKey->withHash('sha256')->withPadding(RSA::SIGNATURE_PSS)
                ->withMGFHash('sha256')->withSaltLength(32);
            if (!method_exists($publicKey, 'getLength') || $publicKey->getLength() < 3072) {
                throw new InvalidArgumentException('平台 RSA 公钥必须为 RSA-3072 或更高强度。');
            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('平台公钥必须是有效的 RSA 公钥 PEM。', 0, $exception);
        }

        $id = Db::transaction(function () use ($keyId, $publicKeyPem, $keyReference, $isPrimary, $actorId): int {
            if ($isPrimary) {
                Db::execute('UPDATE auth_platform_signing_keys SET is_primary = 0 WHERE status = ?', ['ACTIVE']);
            }
            Db::execute(
                'INSERT INTO auth_platform_signing_keys
                 (key_id, public_key_pem, signer_key_ref, algorithm, status, is_primary, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [$keyId, $publicKeyPem, $keyReference, 'RSA-PSS-SHA256', 'ACTIVE', $isPrimary ? 1 : 0, $actorId],
            );
            $newId = (int)Db::getLastInsID();
            Db::execute(
                'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$actorId, 'PLATFORM_SIGNING_KEY_CREATED', 'PLATFORM_SIGNING_KEY', (string)$newId,
                 json_encode(['key_id' => $keyId, 'is_primary' => $isPrimary], JSON_THROW_ON_ERROR)],
            );
            return $newId;
        });
        return $this->find($id);
    }

    /** @return array<string,mixed> */
    public function retire(string $keyId, string $verifyUntil, int $actorId): array
    {
        $key = $this->findByKeyId($keyId);
        $until = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $verifyUntil, new DateTimeZone('UTC'));
        if (!$until || $until <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new InvalidArgumentException('公钥验证保留截止时间必须是未来 UTC 时间（YYYY-MM-DD HH:MM:SS）。');
        }
        $leaseRows = Db::query(
            'SELECT MAX(expires_at) AS max_expires_at FROM auth_offline_license_leases WHERE platform_key_id = ?',
            [(int)$key['id']],
        );
        $maxExpiry = $leaseRows[0]['max_expires_at'] ?? null;
        if (is_string($maxExpiry) && $until->format('Y-m-d H:i:s') < $maxExpiry) {
            throw new InvalidArgumentException('公钥保留时间不能早于该密钥已签发离线凭证的最晚到期时间。');
        }
        Db::execute(
            "UPDATE auth_platform_signing_keys SET status = 'RETIRED', is_primary = 0, verify_until = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [$until->format('Y-m-d H:i:s'), (int)$key['id']],
        );
        $this->audit($actorId, 'PLATFORM_SIGNING_KEY_RETIRED', (int)$key['id'], ['verify_until' => $until->format('Y-m-d H:i:s')]);
        return $this->find((int)$key['id']);
    }

    /** @return array<string,mixed> */
    public function revoke(string $keyId, int $actorId): array
    {
        $key = $this->findByKeyId($keyId);
        Db::execute(
            "UPDATE auth_platform_signing_keys SET status = 'REVOKED', is_primary = 0, verify_until = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [(int)$key['id']],
        );
        $this->audit($actorId, 'PLATFORM_SIGNING_KEY_REVOKED', (int)$key['id'], ['key_id' => $keyId]);
        return $this->find((int)$key['id']);
    }

    /** @return array<string,mixed> */
    private function findByKeyId(string $keyId): array
    {
        $rows = Db::query('SELECT * FROM auth_platform_signing_keys WHERE key_id = ? LIMIT 1', [$keyId]);
        if (!isset($rows[0])) throw new RuntimeException('平台签名密钥不存在。');
        return $rows[0];
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $rows = Db::query(
            'SELECT id, key_id, algorithm, status, is_primary, verify_until, created_at, updated_at
             FROM auth_platform_signing_keys WHERE id = ? LIMIT 1',
            [$id],
        );
        if (!isset($rows[0])) throw new RuntimeException('平台签名密钥不存在。');
        return $rows[0];
    }

    /** @param array<string,mixed> $metadata */
    private function audit(int $actorId, string $event, int $id, array $metadata): void
    {
        Db::execute(
            'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$actorId, $event, 'PLATFORM_SIGNING_KEY', (string)$id,
             json_encode($metadata, JSON_THROW_ON_ERROR)],
        );
    }
}
