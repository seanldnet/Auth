<?php
declare(strict_types=1);

namespace app\license;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class LicenseRenewalService
{
    /** @return array<string,mixed> */
    public function renew(int $licenseId, array $actor, string $idempotencyKey, string $ip, string $userAgent): array
    {
        if ($licenseId < 1 || !preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('授权 ID 或续费幂等键无效。');
        }
        $actorId = (int)($actor['id'] ?? 0);
        $actorRole = (string)($actor['role'] ?? '');
        if ($actorId < 1 || !in_array($actorRole, ['ADMIN','AGENT','SUB_AGENT'], true)) {
            throw new RuntimeException('当前账号没有授权续费权限。');
        }

        return Db::transaction(function () use ($licenseId, $actorId, $actorRole, $idempotencyKey, $ip, $userAgent): array {
            $duplicate = Db::query('SELECT id FROM auth_license_renewals WHERE idempotency_key = ? LIMIT 1', [$idempotencyKey]);
            if ($duplicate !== []) throw new RuntimeException('该续费请求已处理，请勿重复提交。');

            $rows = Db::query('SELECT * FROM auth_licenses WHERE id = ? LIMIT 1 FOR UPDATE', [$licenseId]);
            $license = $rows[0] ?? null;
            if (!is_array($license)) throw new RuntimeException('授权记录不存在。');
            if (!in_array((string)$license['status'], ['ACTIVE','EXPIRED'], true)) {
                throw new RuntimeException('只有正常或已过期授权可以续费；冻结或作废授权不能续费。');
            }
            if ($actorRole !== 'ADMIN' && (int)$license['issued_by_user_id'] !== $actorId) {
                throw new RuntimeException('代理只能续费本人签发的授权。');
            }

            $planRows = Db::query(
                "SELECT p.id, p.duration_unit, p.duration_value, p.status,
                        s.renew_consumes_quota, s.status AS software_status
                 FROM auth_license_plans p
                 INNER JOIN auth_software_apps s ON s.id = p.software_id
                 WHERE p.id = ? AND p.software_id = ? LIMIT 1 FOR UPDATE",
                [(int)$license['plan_id'], (int)$license['software_id']],
            );
            $plan = $planRows[0] ?? null;
            if (!is_array($plan) || $plan['status'] !== 'ACTIVE' || $plan['software_status'] !== 'ACTIVE') {
                throw new RuntimeException('授权对应的软件或套餐已停用，暂时不能续费。');
            }

            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $previous = $license['expires_at'] !== null ? new DateTimeImmutable((string)$license['expires_at'], new DateTimeZone('UTC')) : null;
            $unit = (string)$plan['duration_unit'];
            if ($unit === 'PERMANENT') {
                throw new RuntimeException('永久授权无需续费。');
            } else {
                if ($previous === null) throw new RuntimeException('永久授权无需续费，不能改为有限期限套餐。');
                $base = $previous > $now ? $previous : $now;
                $nextExpiry = LicenseTermCalculator::expiresAt($base, $unit, (int)$plan['duration_value']);
            }

            $quotaConsumed = false;
            if ((int)$plan['renew_consumes_quota'] === 1 && $actorRole !== 'ADMIN') {
                $quotaRows = Db::query(
                    'SELECT id, available_quantity, consumed_quantity
                     FROM auth_agent_license_quotas
                     WHERE user_id = ? AND software_id = ? AND plan_id = ?
                     LIMIT 1 FOR UPDATE',
                    [$actorId, (int)$license['software_id'], (int)$license['plan_id']],
                );
                $quota = $quotaRows[0] ?? null;
                if (!is_array($quota) || (int)$quota['available_quantity'] < 1) {
                    throw new RuntimeException('该软件已设置续费消耗额度，当前可用额度不足。');
                }
                $before = (int)$quota['available_quantity'];
                $after = $before - 1;
                Db::execute(
                    'UPDATE auth_agent_license_quotas
                     SET available_quantity = available_quantity - 1,
                         consumed_quantity = consumed_quantity + 1,
                         updated_at = UTC_TIMESTAMP() WHERE id = ?',
                    [(int)$quota['id']],
                );
                Db::execute(
                    'INSERT INTO auth_agent_license_quota_transactions
                     (quota_id, user_id, software_id, plan_id, direction, quantity, available_before,
                      available_after, actor_user_id, business_type, business_id, idempotency_key, description, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                    [(int)$quota['id'], $actorId, (int)$license['software_id'], (int)$license['plan_id'],
                     'CONSUME', $before, $after, $actorId, 'LICENSE_RENEWAL', $licenseId,
                     $idempotencyKey, '代理续费授权，扣减 1 个额度'],
                );
                $quotaConsumed = true;
            }

            $previousText = $previous?->format('Y-m-d H:i:s');
            $nextText = $nextExpiry?->format('Y-m-d H:i:s');
            Db::execute(
                'UPDATE auth_licenses SET status = ?, expires_at = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                ['ACTIVE', $nextText, $licenseId],
            );
            Db::execute(
                'INSERT INTO auth_license_renewals
                 (license_id, actor_user_id, plan_id, previous_expires_at, new_expires_at,
                  quota_consumed, idempotency_key, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$licenseId, $actorId, (int)$license['plan_id'], $previousText, $nextText, $quotaConsumed ? 1 : 0, $idempotencyKey],
            );
            Db::execute(
                'INSERT INTO auth_license_events
                 (license_id, actor_user_id, event_type, before_state, after_state, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$licenseId, $actorId, 'LICENSE_RENEWED',
                 json_encode(['status' => $license['status'], 'expires_at' => $previousText], JSON_THROW_ON_ERROR),
                 json_encode(['status' => 'ACTIVE', 'expires_at' => $nextText, 'quota_consumed' => $quotaConsumed], JSON_THROW_ON_ERROR),
                 $ip !== '' ? $ip : null, mb_substr($userAgent, 0, 255, 'UTF-8')],
            );

            return [
                'license_id' => $licenseId,
                'license_no' => (string)$license['license_no'],
                'status' => 'ACTIVE',
                'previous_expires_at' => $previousText,
                'expires_at' => $nextText,
                'quota_consumed' => $quotaConsumed,
            ];
        });
    }

}
