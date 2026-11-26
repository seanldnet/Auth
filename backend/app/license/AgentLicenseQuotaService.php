<?php
declare(strict_types=1);

namespace app\license;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class AgentLicenseQuotaService
{
    /** @return array<string,mixed> */
    public function allocate(
        int $targetUserId,
        int $softwareId,
        int $planId,
        int $quantity,
        int $actorId,
        string $idempotencyKey,
        string $description = '',
    ): array {
        if ($targetUserId < 1 || $softwareId < 1 || $planId < 1 || $quantity < 1 || $quantity > 1000000) {
            throw new InvalidArgumentException('代理、软件、套餐或分配数量无效。');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('请提供有效的幂等键。');
        }

        return Db::transaction(function () use (
            $targetUserId, $softwareId, $planId, $quantity, $actorId, $idempotencyKey, $description
        ): array {
            $targetRows = Db::query(
                "SELECT id, role, status FROM auth_users WHERE id = ? LIMIT 1 FOR UPDATE",
                [$targetUserId],
            );
            $target = $targetRows[0] ?? null;
            if (!is_array($target) || !in_array($target['role'], ['AGENT', 'SUB_AGENT'], true)
                || $target['status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('额度只能分配给启用状态的代理或下级代理。');
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

            $duplicate = Db::query(
                'SELECT id FROM auth_agent_license_quota_transactions WHERE idempotency_key = ? LIMIT 1',
                [$idempotencyKey],
            );
            if ($duplicate !== []) {
                throw new RuntimeException('该幂等键已被使用，请勿重复提交额度分配。');
            }

            Db::execute(
                'INSERT INTO auth_agent_license_quotas
                    (user_id, software_id, plan_id, available_quantity, allocated_quantity, consumed_quantity, created_at, updated_at)
                 VALUES (?, ?, ?, 0, 0, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                [$targetUserId, $softwareId, $planId],
            );
            $quotaId = (int) Db::getLastInsID();
            $quotaRows = Db::query(
                'SELECT * FROM auth_agent_license_quotas WHERE id = ? LIMIT 1 FOR UPDATE',
                [$quotaId],
            );
            $quota = $quotaRows[0] ?? null;
            if (!is_array($quota)) {
                throw new RuntimeException('无法锁定代理额度账户。');
            }

            $before = (int) $quota['available_quantity'];
            $after = $before + $quantity;
            Db::execute(
                'UPDATE auth_agent_license_quotas
                 SET available_quantity = available_quantity + ?,
                     allocated_quantity = allocated_quantity + ?,
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = ?',
                [$quantity, $quantity, $quotaId],
            );
            Db::execute(
                'INSERT INTO auth_agent_license_quota_transactions
                    (quota_id, user_id, software_id, plan_id, direction, quantity,
                     available_before, available_after, actor_user_id, business_type,
                     business_id, idempotency_key, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, UTC_TIMESTAMP())',
                [
                    $quotaId, $targetUserId, $softwareId, $planId, 'ALLOCATE', $quantity,
                    $before, $after, $actorId, 'ADMIN_ALLOCATION', $idempotencyKey,
                    mb_substr(trim($description) ?: '管理员分配授权额度', 0, 255, 'UTF-8'),
                ],
            );

            return [
                'quota_id' => $quotaId,
                'user_id' => $targetUserId,
                'software_id' => $softwareId,
                'plan_id' => $planId,
                'available_quantity' => $after,
                'allocated_quantity' => (int) $quota['allocated_quantity'] + $quantity,
                'consumed_quantity' => (int) $quota['consumed_quantity'],
            ];
        });
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,page_size:int} */
    public function transactions(array $actor, int $quotaId, int $page = 1, int $pageSize = 20): array
    {
        if ($quotaId < 1) throw new InvalidArgumentException('额度账户 ID 无效。');
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $where = ['quota_id = ?'];
        $params = [$quotaId];
        if (($actor['role'] ?? '') !== 'ADMIN') {
            if (!in_array(($actor['role'] ?? ''), ['AGENT', 'SUB_AGENT'], true)) {
                throw new RuntimeException('当前账号没有查看额度流水的权限。');
            }
            $where[] = 'user_id = ?';
            $params[] = (int)$actor['id'];
        }
        $clause = implode(' AND ', $where);
        $count = Db::query("SELECT COUNT(*) AS aggregate FROM auth_agent_license_quota_transactions WHERE {$clause}", $params);
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT id, direction, quantity, available_before, available_after,
                    actor_user_id, business_type, business_id, description, created_at
             FROM auth_agent_license_quota_transactions
             WHERE {$clause} ORDER BY id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,page_size:int} */
    public function list(array $actor, int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $where = [];
        $params = [];
        if (($actor['role'] ?? '') !== 'ADMIN') {
            if (!in_array(($actor['role'] ?? ''), ['AGENT', 'SUB_AGENT'], true)) {
                throw new RuntimeException('当前账号没有查看额度的权限。');
            }
            $where[] = 'q.user_id = ?';
            $params[] = (int) $actor['id'];
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = Db::query(
            "SELECT COUNT(*) AS aggregate FROM auth_agent_license_quotas q{$clause}",
            $params,
        );
        $total = (int) ($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT q.id, q.user_id, u.username, u.role, q.software_id, s.name AS software_name,
                    q.plan_id, p.name AS plan_name, q.available_quantity, q.allocated_quantity,
                    q.consumed_quantity, q.updated_at
             FROM auth_agent_license_quotas q
             INNER JOIN auth_users u ON u.id = q.user_id
             INNER JOIN auth_software_apps s ON s.id = q.software_id
             INNER JOIN auth_license_plans p ON p.id = q.plan_id
             {$clause}
             ORDER BY q.updated_at DESC, q.id DESC
             LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
