<?php
declare(strict_types=1);

namespace app\license;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class LicenseReplacementService
{
    /** @return array<string,mixed> */
    public function replace(int $licenseId, array $actor, string $idempotencyKey, string $ip, string $userAgent): array
    {
        if ($licenseId < 1 || !preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('授权 ID 或更换幂等键无效。');
        }
        $actorId = (int)($actor['id'] ?? 0);
        $role = (string)($actor['role'] ?? '');
        if ($actorId < 1 || !in_array($role, ['ADMIN','AGENT','SUB_AGENT'], true)) {
            throw new RuntimeException('当前账号没有更换授权的权限。');
        }

        return Db::transaction(function () use ($licenseId, $actorId, $role, $idempotencyKey, $ip, $userAgent): array {
            $duplicate = Db::query('SELECT id FROM auth_license_replacements WHERE idempotency_key = ? LIMIT 1', [$idempotencyKey]);
            if ($duplicate !== []) throw new RuntimeException('该更换请求已处理，请勿重复提交。');

            $rows = Db::query('SELECT * FROM auth_licenses WHERE id = ? LIMIT 1 FOR UPDATE', [$licenseId]);
            $old = $rows[0] ?? null;
            if (!is_array($old)) throw new RuntimeException('原授权记录不存在。');
            if (!in_array((string)$old['status'], ['GENERATED','ACTIVE'], true)) {
                throw new RuntimeException('只有待激活或正常授权可以更换；冻结、作废或过期授权不能更换。');
            }
            $issuerId = (int)$old['issued_by_user_id'];
            if ($role !== 'ADMIN' && $issuerId !== $actorId) {
                throw new RuntimeException('代理只能更换本人签发的授权。');
            }

            $plainKey = $this->newLicenseKey();
            $newLicenseNo = 'LIC-' . strtoupper(bin2hex(random_bytes(6)));
            $hint = substr($plainKey, 0, 4) . '...' . substr($plainKey, -4);
            Db::execute(
                'INSERT INTO auth_licenses
                 (license_no, license_key_hash, license_key_hint, software_id, plan_id, owner_user_id,
                  issued_by_user_id, status, frozen_from_status, device_fingerprint_hash, device_label,
                  activated_at, expires_at, machine_change_count, last_verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [$newLicenseNo, hash('sha256', $plainKey), $hint, (int)$old['software_id'], (int)$old['plan_id'],
                 $old['owner_user_id'] !== null ? (int)$old['owner_user_id'] : null, $issuerId,
                 (string)$old['status'], $old['device_fingerprint_hash'], $old['device_label'],
                 $old['activated_at'], $old['expires_at'], (int)$old['machine_change_count'], $old['last_verified_at']],
            );
            $newId = (int)Db::getLastInsID();

            $this->transferQuotaConsumption($old, $newId, $issuerId, $actorId);

            Db::execute(
                'UPDATE auth_licenses SET status = ?, frozen_from_status = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                ['VOID', $licenseId],
            );
            Db::execute(
                'INSERT INTO auth_license_replacements
                 (old_license_id, new_license_id, actor_user_id, idempotency_key, created_at)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                [$licenseId, $newId, $actorId, $idempotencyKey],
            );

            $before = ['status' => (string)$old['status'], 'license_no' => (string)$old['license_no']];
            $after = ['status' => 'VOID', 'replacement_license_id' => $newId, 'replacement_license_no' => $newLicenseNo];
            $this->event($licenseId, $actorId, 'LICENSE_REPLACED_OUT', $before, $after, $ip, $userAgent);
            $this->event($newId, $actorId, 'LICENSE_REPLACEMENT_CREATED', null, [
                'status' => (string)$old['status'], 'replaced_license_id' => $licenseId,
                'replaced_license_no' => (string)$old['license_no'],
            ], $ip, $userAgent);

            return [
                'old_license_id' => $licenseId,
                'old_license_no' => (string)$old['license_no'],
                'new_license_id' => $newId,
                'new_license_no' => $newLicenseNo,
                'license_key' => $plainKey,
                'status' => (string)$old['status'],
                'expires_at' => $old['expires_at'],
            ];
        });
    }

    /** @param array<string,mixed> $old */
    private function transferQuotaConsumption(array $old, int $newLicenseId, int $issuerId, int $actorId): void
    {
        $issuer = Db::query("SELECT role FROM auth_users WHERE id = ? LIMIT 1", [$issuerId]);
        if (!isset($issuer[0]) || !in_array($issuer[0]['role'], ['AGENT','SUB_AGENT'], true)) return;

        $rows = Db::query(
            "SELECT COALESCE(SUM(quantity), 0) AS aggregate FROM auth_agent_license_quota_transactions
             WHERE business_id = ? AND direction = 'CONSUME'
               AND business_type IN ('LICENSE_ISSUE','LICENSE_RENEWAL','LICENSE_REPLACEMENT_IN')",
            [(int)$old['id']],
        );
        $quantity = (int)($rows[0]['aggregate'] ?? 0);
        if ($quantity < 1) return;

        $quotaRows = Db::query(
            'SELECT id, available_quantity, consumed_quantity FROM auth_agent_license_quotas
             WHERE user_id = ? AND software_id = ? AND plan_id = ? LIMIT 1 FOR UPDATE',
            [$issuerId, (int)$old['software_id'], (int)$old['plan_id']],
        );
        $quota = $quotaRows[0] ?? null;
        if (!is_array($quota) || (int)$quota['consumed_quantity'] < $quantity) {
            throw new RuntimeException('原授权额度账本不一致，无法安全更换授权。');
        }

        $before = (int)$quota['available_quantity'];
        $middle = $before + $quantity;
        $after = $middle - $quantity;
        $outKey = hash('sha256', 'license-replacement-out:' . (int)$old['id']);
        $inKey = hash('sha256', 'license-replacement-in:' . $newLicenseId);

        Db::execute(
            'UPDATE auth_agent_license_quotas SET available_quantity = available_quantity + ?,
             consumed_quantity = consumed_quantity - ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$quantity, $quantity, (int)$quota['id']],
        );
        Db::execute(
            'INSERT INTO auth_agent_license_quota_transactions
             (quota_id, user_id, software_id, plan_id, direction, quantity, available_before,
              available_after, actor_user_id, business_type, business_id, idempotency_key, description, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [(int)$quota['id'], $issuerId, (int)$old['software_id'], (int)$old['plan_id'],
             'REVERSAL', $quantity, $before, $middle, $actorId, 'LICENSE_REPLACEMENT_OUT',
             (int)$old['id'], $outKey, '更换授权，转出原授权额度'],
        );
        Db::execute(
            'UPDATE auth_agent_license_quotas SET available_quantity = available_quantity - ?,
             consumed_quantity = consumed_quantity + ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$quantity, $quantity, (int)$quota['id']],
        );
        Db::execute(
            'INSERT INTO auth_agent_license_quota_transactions
             (quota_id, user_id, software_id, plan_id, direction, quantity, available_before,
              available_after, actor_user_id, business_type, business_id, idempotency_key, description, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [(int)$quota['id'], $issuerId, (int)$old['software_id'], (int)$old['plan_id'],
             'CONSUME', $quantity, $middle, $after, $actorId, 'LICENSE_REPLACEMENT_IN',
             $newLicenseId, $inKey, '更换授权，新授权继承原授权额度占用'],
        );
    }

    private function newLicenseKey(): string
    {
        return implode('-', str_split(strtoupper(bin2hex(random_bytes(16))), 8));
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    private function event(int $licenseId, int $actorId, string $type, ?array $before, array $after, string $ip, string $userAgent): void
    {
        Db::execute(
            'INSERT INTO auth_license_events
             (license_id, actor_user_id, event_type, before_state, after_state, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$licenseId, $actorId, $type,
             $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
             json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
             $ip !== '' ? $ip : null, mb_substr($userAgent, 0, 255, 'UTF-8')],
        );
    }
}
