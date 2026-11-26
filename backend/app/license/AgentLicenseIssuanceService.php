<?php
declare(strict_types=1);

namespace app\license;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class AgentLicenseIssuanceService
{
    /** @return array{licenses:list<array{id:int,license_no:string,license_key:string}>,quantity:int,available_quantity:int} */
    public function issue(int $actorId, int $softwareId, int $planId, int $quantity, string $idempotencyKey, string $ip, string $userAgent): array
    {
        if ($actorId < 1 || $softwareId < 1 || $planId < 1 || $quantity < 1 || $quantity > 500) {
            throw new InvalidArgumentException('软件、套餐或生成数量无效；单次最多生成 500 个授权码。');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('请提供有效的幂等键。');
        }

        return Db::transaction(function () use ($actorId, $softwareId, $planId, $quantity, $idempotencyKey, $ip, $userAgent): array {
            $actorRows = Db::query('SELECT id, role, status FROM auth_users WHERE id = ? LIMIT 1 FOR UPDATE', [$actorId]);
            $actor = $actorRows[0] ?? null;
            if (!is_array($actor) || !in_array($actor['role'], ['AGENT', 'SUB_AGENT'], true) || $actor['status'] !== 'ACTIVE') {
                throw new RuntimeException('只有启用状态的代理或下级代理可以使用授权额度。');
            }

            $planRows = Db::query(
                "SELECT p.id, p.software_id, p.status, s.status AS software_status
                 FROM auth_license_plans p
                 INNER JOIN auth_software_apps s ON s.id = p.software_id
                 WHERE p.id = ? AND p.software_id = ? LIMIT 1 FOR UPDATE",
                [$planId, $softwareId],
            );
            $plan = $planRows[0] ?? null;
            if (!is_array($plan) || $plan['status'] !== 'ACTIVE' || $plan['software_status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('软件或套餐不存在、未启用，或套餐不属于该软件。');
            }

            $duplicate = Db::query('SELECT id FROM auth_agent_license_quota_transactions WHERE idempotency_key = ? LIMIT 1', [$idempotencyKey]);
            if ($duplicate !== []) {
                throw new RuntimeException('该请求已处理，请勿重复提交。');
            }

            $quotaRows = Db::query(
                'SELECT * FROM auth_agent_license_quotas WHERE user_id = ? AND software_id = ? AND plan_id = ? LIMIT 1 FOR UPDATE',
                [$actorId, $softwareId, $planId],
            );
            $quota = $quotaRows[0] ?? null;
            if (!is_array($quota) || (int)$quota['available_quantity'] < $quantity) {
                throw new RuntimeException('可用授权额度不足。');
            }

            $before = (int)$quota['available_quantity'];
            $after = $before - $quantity;
            $created = [];
            for ($i = 0; $i < $quantity; $i++) {
                $plainKey = implode('-', str_split(strtoupper(bin2hex(random_bytes(16))), 8));
                $licenseNo = 'LIC-' . strtoupper(bin2hex(random_bytes(6)));
                Db::execute(
                    'INSERT INTO auth_licenses
                     (license_no, license_key_hash, license_key_hint, software_id, plan_id,
                      issued_by_user_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                    [$licenseNo, hash('sha256', $plainKey), substr($plainKey, 0, 4) . '...' . substr($plainKey, -4),
                     $softwareId, $planId, $actorId, 'GENERATED'],
                );
                $licenseId = (int)Db::getLastInsID();
                $created[] = ['id' => $licenseId, 'license_no' => $licenseNo, 'license_key' => $plainKey];
                Db::execute(
                    'INSERT INTO auth_license_events
                     (license_id, actor_user_id, event_type, before_state, after_state, ip_address, user_agent, created_at)
                     VALUES (?, ?, ?, NULL, ?, ?, ?, UTC_TIMESTAMP())',
                    [$licenseId, $actorId, 'LICENSE_GENERATED',
                     json_encode(['software_id' => $softwareId, 'plan_id' => $planId, 'status' => 'GENERATED'], JSON_THROW_ON_ERROR),
                     $ip !== '' ? $ip : null, mb_substr($userAgent, 0, 255, 'UTF-8')],
                );
            }

            Db::execute(
                'UPDATE auth_agent_license_quotas
                 SET available_quantity = available_quantity - ?, consumed_quantity = consumed_quantity + ?,
                     updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$quantity, $quantity, (int)$quota['id']],
            );
            Db::execute(
                'INSERT INTO auth_agent_license_quota_transactions
                 (quota_id, user_id, software_id, plan_id, direction, quantity, available_before,
                  available_after, actor_user_id, business_type, business_id, idempotency_key, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [(int)$quota['id'], $actorId, $softwareId, $planId, 'CONSUME', $quantity, $before, $after,
                 $actorId, 'LICENSE_ISSUE', (int)$created[0]['id'], $idempotencyKey,
                 '代理批量生成授权码：' . $quantity . ' 个'],
            );

            return ['licenses' => $created, 'quantity' => count($created), 'available_quantity' => $after];
        });
    }
}
