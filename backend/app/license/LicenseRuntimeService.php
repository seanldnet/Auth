<?php
declare(strict_types=1);

namespace app\license;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class LicenseRuntimeService
{
    /** @return array<string,mixed> */
    public function activate(string $appId, string $licenseKey, string $fingerprint, string $deviceLabel, string $ip, string $agent): array
    {
        $software = $this->software($appId);
        $fingerprintHash = $this->fingerprintHash($fingerprint);
        $licenseHash = $this->licenseHash($licenseKey);
        return Db::transaction(function () use ($software, $fingerprintHash, $deviceLabel, $licenseHash, $ip, $agent): array {
            $rows = Db::query('SELECT * FROM auth_licenses WHERE license_key_hash = ? AND software_id = ? LIMIT 1 FOR UPDATE', [$licenseHash, (int)$software['id']]);
            $license = $rows[0] ?? null;
            if (!is_array($license)) throw new RuntimeException('授权码无效。');
            if ($license['status'] === 'FROZEN') throw new RuntimeException('授权已被冻结。');
            if ($license['status'] === 'VOID') throw new RuntimeException('授权已作废。');
            if ($license['status'] === 'EXPIRED') throw new RuntimeException('授权已过期。');
            if ($license['status'] === 'ACTIVE') {
                if ($this->expireIfNeeded($license, $ip, $agent)) {
                    return ['valid' => false, 'status' => 'EXPIRED', 'message' => '授权已过期'];
                }
                if (hash_equals((string)$license['device_fingerprint_hash'], $fingerprintHash)) {
                    return $this->runtimeResult($license, true);
                }
                throw new RuntimeException('授权已绑定其他设备，请先执行换机操作。');
            }
            $planRows = Db::query(
                'SELECT duration_unit, duration_value, status FROM auth_license_plans WHERE id = ? AND software_id = ? LIMIT 1',
                [(int)$license['plan_id'], (int)$software['id']],
            );
            $plan = $planRows[0] ?? null;
            if (!is_array($plan) || $plan['status'] !== 'ACTIVE') throw new RuntimeException('授权套餐已停用，无法激活。');
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $expiresAt = $this->expiry($now, (string)$plan['duration_unit'], $plan['duration_value'] !== null ? (int)$plan['duration_value'] : null);
            Db::execute(
                'UPDATE auth_licenses SET status = ?, device_fingerprint_hash = ?, device_label = ?,
                 activated_at = ?, expires_at = ?, last_verified_at = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                ['ACTIVE', $fingerprintHash, $this->deviceLabel($deviceLabel), $now->format('Y-m-d H:i:s'),
                 $expiresAt?->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), (int)$license['id']],
            );
            $this->event((int)$license['id'], 'LICENSE_ACTIVATED', ['status' => 'GENERATED'], [
                'status' => 'ACTIVE', 'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
                'device_fingerprint_hash' => $fingerprintHash,
            ], $ip, $agent);
            $license['status'] = 'ACTIVE';
            $license['device_fingerprint_hash'] = $fingerprintHash;
            $license['device_label'] = $this->deviceLabel($deviceLabel);
            $license['activated_at'] = $now->format('Y-m-d H:i:s');
            $license['expires_at'] = $expiresAt?->format('Y-m-d H:i:s');
            return $this->runtimeResult($license, false);
        });
    }

    /** @return array<string,mixed> */
    public function verify(string $appId, string $licenseKey, string $fingerprint, string $ip, string $agent): array
    {
        $software = $this->software($appId);
        $fingerprintHash = $this->fingerprintHash($fingerprint);
        $licenseHash = $this->licenseHash($licenseKey);
        return Db::transaction(function () use ($software, $fingerprintHash, $licenseHash, $ip, $agent): array {
            $rows = Db::query('SELECT * FROM auth_licenses WHERE license_key_hash = ? AND software_id = ? LIMIT 1 FOR UPDATE', [$licenseHash, (int)$software['id']]);
            $license = $rows[0] ?? null;
            if (!is_array($license)) throw new RuntimeException('授权码无效。');
            if ($license['status'] !== 'ACTIVE') throw new RuntimeException($this->statusMessage((string)$license['status']));
            if (!hash_equals((string)$license['device_fingerprint_hash'], $fingerprintHash)) throw new RuntimeException('设备指纹不匹配。');
            if ($this->expireIfNeeded($license, $ip, $agent)) {
                return ['valid' => false, 'status' => 'EXPIRED', 'message' => '授权已过期'];
            }
            Db::execute('UPDATE auth_licenses SET last_verified_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$license['id']]);
            return $this->runtimeResult($license, true);
        });
    }

    /** @return array<string,mixed> */
    public function changeDevice(string $appId, string $licenseKey, string $currentFingerprint, string $newFingerprint, string $deviceLabel, string $ip, string $agent): array
    {
        $software = $this->software($appId);
        $currentHash = $this->fingerprintHash($currentFingerprint);
        $newHash = $this->fingerprintHash($newFingerprint);
        if (hash_equals($currentHash, $newHash)) throw new InvalidArgumentException('新设备指纹与当前设备相同。');
        $licenseHash = $this->licenseHash($licenseKey);
        return Db::transaction(function () use ($software, $currentHash, $newHash, $deviceLabel, $licenseHash, $ip, $agent): array {
            $rows = Db::query('SELECT * FROM auth_licenses WHERE license_key_hash = ? AND software_id = ? LIMIT 1 FOR UPDATE', [$licenseHash, (int)$software['id']]);
            $license = $rows[0] ?? null;
            if (!is_array($license) || $license['status'] !== 'ACTIVE') throw new RuntimeException('授权不存在或当前不可换机。');
            if (!hash_equals((string)$license['device_fingerprint_hash'], $currentHash)) throw new RuntimeException('当前设备指纹不匹配，不能换机。');
            if ($this->expireIfNeeded($license, $ip, $agent)) return ['valid' => false, 'status' => 'EXPIRED', 'message' => '授权已过期'];
            Db::execute(
                'UPDATE auth_licenses SET device_fingerprint_hash = ?, device_label = ?,
                 machine_change_count = machine_change_count + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$newHash, $this->deviceLabel($deviceLabel), (int)$license['id']],
            );
            $this->event((int)$license['id'], 'LICENSE_DEVICE_CHANGED', [
                'device_fingerprint_hash' => $currentHash, 'machine_change_count' => (int)$license['machine_change_count'],
            ], [
                'device_fingerprint_hash' => $newHash, 'machine_change_count' => (int)$license['machine_change_count'] + 1,
            ], $ip, $agent);
            $license['device_fingerprint_hash'] = $newHash;
            $license['device_label'] = $this->deviceLabel($deviceLabel);
            $license['machine_change_count'] = (int)$license['machine_change_count'] + 1;
            return $this->runtimeResult($license, false);
        });
    }

    /** @return array<string,mixed> */
    public function unbind(string $appId, string $licenseKey, string $fingerprint, string $ip, string $agent): array
    {
        $software = $this->software($appId);
        $fingerprintHash = $this->fingerprintHash($fingerprint);
        $licenseHash = $this->licenseHash($licenseKey);
        return Db::transaction(function () use ($software, $fingerprintHash, $licenseHash, $ip, $agent): array {
            $rows = Db::query('SELECT * FROM auth_licenses WHERE license_key_hash = ? AND software_id = ? LIMIT 1 FOR UPDATE', [$licenseHash, (int)$software['id']]);
            $license = $rows[0] ?? null;
            if (!is_array($license) || $license['status'] !== 'ACTIVE') throw new RuntimeException('授权不存在或当前不可解绑。');
            if (!hash_equals((string)$license['device_fingerprint_hash'], $fingerprintHash)) throw new RuntimeException('设备指纹不匹配，不能解绑。');
            $this->assertNotExpired($license);
            Db::execute('UPDATE auth_licenses SET device_fingerprint_hash = NULL, device_label = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$license['id']]);
            $this->event((int)$license['id'], 'LICENSE_DEVICE_UNBOUND', ['device_fingerprint_hash' => $fingerprintHash], ['device_fingerprint_hash' => null], $ip, $agent);
            $license['device_fingerprint_hash'] = null;
            $license['device_label'] = null;
            return $this->runtimeResult($license, false);
        });
    }

    /** @return array<string,mixed> */
    private function software(string $appId): array
    {
        $appId = trim($appId);
        if (!preg_match('/^app_[a-f0-9]{24}$/i', $appId)) throw new InvalidArgumentException('软件 App ID 格式无效。');
        $rows = Db::query("SELECT id, app_id, status FROM auth_software_apps WHERE app_id = ? LIMIT 1", [$appId]);
        $software = $rows[0] ?? null;
        if (!is_array($software) || $software['status'] !== 'ACTIVE') throw new RuntimeException('软件不存在或当前未启用。');
        return $software;
    }

    private function licenseHash(string $key): string
    {
        $key = strtoupper(trim($key));
        if (!preg_match('/^[A-F0-9]{8}(?:-[A-F0-9]{8}){3}$/', $key)) throw new InvalidArgumentException('授权码格式无效。');
        return hash('sha256', $key);
    }

    private function fingerprintHash(string $fingerprint): string
    {
        $fingerprint = trim($fingerprint);
        if (strlen($fingerprint) < 8 || strlen($fingerprint) > 256) throw new InvalidArgumentException('设备指纹长度必须为 8 至 256 个字符。');
        return hash('sha256', $fingerprint);
    }

    private function deviceLabel(string $label): ?string
    {
        $label = trim($label);
        if (mb_strlen($label) > 120) throw new InvalidArgumentException('设备名称不能超过 120 个字符。');
        return $label !== '' ? $label : null;
    }

    private function expiry(DateTimeImmutable $start, string $unit, ?int $value): ?DateTimeImmutable
    {
        if ($unit === 'PERMANENT') return null;
        if ($value === null || $value < 1 || $value > 1200) throw new RuntimeException('授权套餐时长配置异常。');
        if ($unit === 'DAY') return $start->modify('+' . $value . ' days');
        $months = match ($unit) {
            'MONTH' => $value,
            'YEAR' => $value * 12,
            default => throw new RuntimeException('授权套餐时长单位无效。'),
        };
        $target = $start->modify('first day of this month')->modify('+' . $months . ' months');
        $day = min((int)$start->format('d'), (int)$target->format('t'));
        return $target->setDate((int)$target->format('Y'), (int)$target->format('m'), $day)
            ->setTime((int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s'));
    }

    /** @param array<string,mixed> $license */
    private function expireIfNeeded(array $license, string $ip, string $agent): bool
    {
        if ($license['expires_at'] === null || strtotime((string)$license['expires_at'] . ' UTC') > time()) return false;
        Db::execute("UPDATE auth_licenses SET status = 'EXPIRED', updated_at = UTC_TIMESTAMP() WHERE id = ?", [(int)$license['id']]);
        $this->event((int)$license['id'], 'LICENSE_EXPIRED', ['status' => 'ACTIVE'], ['status' => 'EXPIRED'], $ip, $agent);
        return true;
    }

    /** @param array<string,mixed> $license @return array<string,mixed> */
    private function runtimeResult(array $license, bool $idempotent): array
    {
        return [
            'valid' => true,
            'license_no' => (string)$license['license_no'],
            'status' => (string)$license['status'],
            'activated_at' => $license['activated_at'],
            'expires_at' => $license['expires_at'],
            'device_label' => $license['device_label'],
            'machine_change_count' => (int)$license['machine_change_count'],
            'idempotent' => $idempotent,
        ];
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'GENERATED' => '授权尚未激活。',
            'FROZEN' => '授权已被冻结。',
            'VOID' => '授权已作废。',
            'EXPIRED' => '授权已过期。',
            default => '授权当前不可用。',
        };
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private function event(int $licenseId, string $type, array $before, array $after, string $ip, string $agent): void
    {
        Db::execute(
            'INSERT INTO auth_license_events (license_id, actor_user_id, event_type, before_state, after_state, ip_address, user_agent, created_at)
             VALUES (?, NULL, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$licenseId, $type, json_encode($before, JSON_THROW_ON_ERROR), json_encode($after, JSON_THROW_ON_ERROR),
             $ip ?: null, mb_substr($agent, 0, 255, 'UTF-8')],
        );
    }
}
