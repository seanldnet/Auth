<?php
declare(strict_types=1);

namespace app\license;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use app\security\PlatformSignatureService;
use think\facade\Db;

final class OfflineLicenseLeaseService
{
    public function __construct(private readonly ?PlatformSignatureService $signatures = null) {}

    /** @return array<string,mixed> */
    public function issue(string $appId, string $licenseKey, string $fingerprint, string $ip, string $agent): array
    {
        $appId = trim($appId);
        if (!preg_match('/^app_[a-f0-9]{24}$/i', $appId)) throw new InvalidArgumentException('软件 App ID 格式无效。');
        $licenseKey = strtoupper(trim($licenseKey));
        if (!preg_match('/^[A-F0-9]{8}(?:-[A-F0-9]{8}){3}$/', $licenseKey)) throw new InvalidArgumentException('授权码格式无效。');
        $fingerprint = trim($fingerprint);
        if (strlen($fingerprint) < 8 || strlen($fingerprint) > 256) throw new InvalidArgumentException('设备指纹长度必须为 8 至 256 个字符。');

        $softwareRows = Db::query(
            "SELECT id, app_id, status, offline_days FROM auth_software_apps WHERE app_id = ? LIMIT 1",
            [$appId],
        );
        $software = $softwareRows[0] ?? null;
        if (!is_array($software) || $software['status'] !== 'ACTIVE') throw new RuntimeException('软件不存在或当前未启用。');
        $offlineDays = (int)$software['offline_days'];
        if ($offlineDays < 1 || $offlineDays > 90) throw new RuntimeException('该软件未启用离线授权。');

        $fingerprintHash = hash('sha256', $fingerprint);
        $licenseHash = hash('sha256', $licenseKey);
        $licenseRows = Db::query(
            'SELECT id, license_no, software_id, status, device_fingerprint_hash, expires_at
             FROM auth_licenses WHERE license_key_hash = ? AND software_id = ? LIMIT 1',
            [$licenseHash, (int)$software['id']],
        );
        $license = $licenseRows[0] ?? null;
        if (!is_array($license) || $license['status'] !== 'ACTIVE') throw new RuntimeException('授权不存在或当前不可签发离线凭证。');
        if (!is_string($license['device_fingerprint_hash'])
            || !hash_equals($license['device_fingerprint_hash'], $fingerprintHash)) {
            throw new RuntimeException('设备指纹不匹配，不能签发离线凭证。');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($license['expires_at'] !== null) {
            $licenseExpiry = new DateTimeImmutable((string)$license['expires_at'], new DateTimeZone('UTC'));
            if ($licenseExpiry <= $now) throw new RuntimeException('授权已过期。');
        } else {
            $licenseExpiry = null;
        }

        $keyRows = Db::query(
            "SELECT id, key_id, public_key_pem, signer_key_ref
             FROM auth_platform_signing_keys
             WHERE status = 'ACTIVE' AND is_primary = 1
             ORDER BY id DESC LIMIT 1",
        );
        $platformKey = $keyRows[0] ?? null;
        if (!is_array($platformKey)) throw new RuntimeException('平台离线签名公钥尚未配置，离线授权签发已关闭。');

        $leaseExpiry = $now->modify('+' . $offlineDays . ' days');
        if ($licenseExpiry !== null && $licenseExpiry < $leaseExpiry) $leaseExpiry = $licenseExpiry;
        $issuedAt = $now->getTimestamp();
        $expiresAt = $leaseExpiry->getTimestamp();
        if ($expiresAt <= $issuedAt) throw new RuntimeException('授权剩余有效期不足，无法签发离线凭证。');

        $leaseId = $this->uuid();
        $payload = [
            'version' => 1,
            'lease_id' => $leaseId,
            'app_id' => (string)$software['app_id'],
            'license_no' => (string)$license['license_no'],
            'device_fingerprint_hash' => $fingerprintHash,
            'issued_at' => $issuedAt,
            'not_before' => $issuedAt,
            'expires_at' => $expiresAt,
            'key_id' => (string)$platformKey['key_id'],
            'algorithm' => 'RSA-PSS-SHA256',
        ];
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = ($this->signatures ?? new PlatformSignatureService())->signAndVerify(
            (string)$platformKey['signer_key_ref'],
            (string)$platformKey['public_key_pem'],
            $payloadJson,
        );
        $token = OfflineLicenseToken::encode($payloadJson, $signature);
        $tokenHash = hash('sha256', $token);

        Db::transaction(function () use (
            $software, $license, $platformKey, $fingerprintHash, $tokenHash,
            $leaseId, $issuedAt, $expiresAt, $offlineDays, $ip, $agent
        ): void {
            $currentSoftwareRows = Db::query(
                'SELECT status, offline_days FROM auth_software_apps WHERE id = ? LIMIT 1 FOR UPDATE',
                [(int)$software['id']],
            );
            $currentSoftware = $currentSoftwareRows[0] ?? null;
            $currentLicenseRows = Db::query(
                'SELECT status, device_fingerprint_hash, expires_at FROM auth_licenses WHERE id = ? LIMIT 1 FOR UPDATE',
                [(int)$license['id']],
            );
            $currentLicense = $currentLicenseRows[0] ?? null;
            $currentKeyRows = Db::query(
                'SELECT status FROM auth_platform_signing_keys WHERE id = ? LIMIT 1 FOR UPDATE',
                [(int)$platformKey['id']],
            );
            $currentKey = $currentKeyRows[0] ?? null;
            if (!is_array($currentKey) || $currentKey['status'] !== 'ACTIVE') {
                throw new RuntimeException('平台签名密钥已轮换或撤销，拒绝签发离线凭证。');
            }
            if (!is_array($currentSoftware) || $currentSoftware['status'] !== 'ACTIVE'
                || (int)$currentSoftware['offline_days'] !== $offlineDays) {
                throw new RuntimeException('软件离线授权配置已变化，请重新申请凭证。');
            }
            if (!is_array($currentLicense) || $currentLicense['status'] !== 'ACTIVE'
                || !is_string($currentLicense['device_fingerprint_hash'])
                || !hash_equals($currentLicense['device_fingerprint_hash'], $fingerprintHash)) {
                throw new RuntimeException('授权状态或设备绑定已变化，拒绝签发离线凭证。');
            }
            if ($currentLicense['expires_at'] !== null
                && strtotime((string)$currentLicense['expires_at'] . ' UTC') < $expiresAt) {
                throw new RuntimeException('授权有效期已变化，请重新申请离线凭证。');
            }
            Db::execute(
                'INSERT INTO auth_offline_license_leases
                 (lease_id, license_id, software_id, platform_key_id, device_fingerprint_hash,
                  token_hash, issued_at, not_before, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?), UTC_TIMESTAMP())',
                [$leaseId, (int)$license['id'], (int)$software['id'], (int)$platformKey['id'],
                 $fingerprintHash, $tokenHash, $issuedAt, $issuedAt, $expiresAt],
            );
            Db::execute(
                'INSERT INTO auth_license_events
                 (license_id, actor_user_id, event_type, before_state, after_state, ip_address, user_agent, created_at)
                 VALUES (?, NULL, ?, NULL, ?, ?, ?, UTC_TIMESTAMP())',
                [(int)$license['id'], 'OFFLINE_LEASE_ISSUED',
                 json_encode(['lease_id' => $leaseId, 'expires_at' => $expiresAt, 'offline_days' => $offlineDays], JSON_THROW_ON_ERROR),
                 $ip ?: null, mb_substr($agent, 0, 255, 'UTF-8')],
            );
        });

        return [
            'token' => $token,
            'lease_id' => $leaseId,
            'key_id' => (string)$platformKey['key_id'],
            'algorithm' => 'RSA-PSS-SHA256',
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'offline_days' => (int)ceil(($expiresAt - $issuedAt) / 86400),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function platformKeys(string $appId): array
    {
        if (!preg_match('/^app_[a-f0-9]{24}$/i', trim($appId))) throw new InvalidArgumentException('软件 App ID 格式无效。');
        $softwareRows = Db::query("SELECT id FROM auth_software_apps WHERE app_id = ? AND status = 'ACTIVE' LIMIT 1", [trim($appId)]);
        if (!isset($softwareRows[0])) throw new RuntimeException('软件不存在或当前未启用。');
        return Db::query(
            "SELECT key_id, public_key_pem, algorithm, status, verify_until
             FROM auth_platform_signing_keys
             WHERE status = 'ACTIVE' OR (status = 'RETIRED' AND verify_until > UTC_TIMESTAMP())
             ORDER BY is_primary DESC, id DESC",
        );
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

}
