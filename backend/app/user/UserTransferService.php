<?php
declare(strict_types=1);

namespace app\user;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class UserTransferService
{
    /** @return array<string,mixed> */
    public function requestByUser(int $userId, string $inviteCode, string $reason, string $ip, string $agent): array
    {
        $targets = null;
        if (trim($inviteCode) !== '') {
            $rows = Db::query("SELECT id, role, status FROM auth_users WHERE invite_code = ? LIMIT 1", [trim($inviteCode)]);
            $target = $rows[0] ?? null;
            if (!is_array($target) || !in_array($target['role'], ['AGENT','SUB_AGENT'], true) || $target['status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('目标邀请码无效或代理账号不可用。');
            }
            $targets = (int)$target['id'];
        }
        return $this->createRequest($userId, $targets, $userId, $reason, $ip, $agent);
    }

    /** @return array<string,mixed> */
    public function requestByAdmin(int $userId, ?int $targetId, int $adminId, string $reason, string $ip, string $agent): array
    {
        if ($targetId !== null) $this->assertActiveTarget($targetId);
        return $this->createRequest($userId, $targetId, $adminId, $reason, $ip, $agent);
    }

    /** @return array<string,mixed> */
    private function createRequest(int $userId, ?int $targetId, int $requesterId, string $reason, string $ip, string $agent): array
    {
        if (mb_strlen($reason) > 500) throw new InvalidArgumentException('申请原因不能超过 500 个字符。');
        return Db::transaction(function () use ($userId, $targetId, $requesterId, $reason, $ip, $agent): array {
            $users = Db::query('SELECT id, role, parent_id, status FROM auth_users WHERE id = ? FOR UPDATE', [$userId]);
            $user = $users[0] ?? null;
            if (!is_array($user) || $user['role'] !== 'USER' || $user['status'] !== 'ACTIVE') {
                throw new RuntimeException('用户不存在或账号不可申请变更。');
            }
            if ($targetId === $userId || ($targetId !== null && $targetId === (int)$user['parent_id'])) {
                throw new InvalidArgumentException('目标归属与当前归属相同。');
            }
            if ($targetId !== null) $this->assertActiveTarget($targetId);
            $pending = Db::query("SELECT id FROM auth_user_transfer_requests WHERE user_id = ? AND status = 'PENDING' LIMIT 1 FOR UPDATE", [$userId]);
            if (isset($pending[0])) throw new RuntimeException('该用户已有待审批的归属变更申请。');
            Db::execute(
                'INSERT INTO auth_user_transfer_requests
                 (user_id, from_parent_id, requested_parent_id, requested_by_user_id, request_reason, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [$userId, $user['parent_id'] !== null ? (int)$user['parent_id'] : null, $targetId, $requesterId, $reason !== '' ? $reason : null, 'PENDING'],
            );
            $id = (int)Db::getLastInsID();
            $this->audit($requesterId, 'USER_TRANSFER_REQUESTED', $userId, $ip, $agent, ['request_id' => $id, 'target_parent_id' => $targetId]);
            return $this->findRequest($id);
        });
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function listRequests(int $page, int $pageSize, string $status = 'PENDING'): array
    {
        $page = max(1, $page); $pageSize = min(100, max(1, $pageSize));
        if (!in_array($status, ['PENDING','APPROVED','REJECTED','CANCELLED','ALL'], true)) throw new InvalidArgumentException('申请状态无效。');
        $where = $status === 'ALL' ? '' : 'WHERE r.status = ?';
        $params = $status === 'ALL' ? [] : [$status];
        $count = Db::query("SELECT COUNT(*) AS aggregate FROM auth_user_transfer_requests r {$where}", $params);
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT r.id, r.user_id, u.username, r.from_parent_id, old_parent.username AS from_parent_username,
                    r.requested_parent_id, new_parent.username AS requested_parent_username,
                    r.requested_by_user_id, requester.username AS requester_username, r.request_reason,
                    r.status, r.review_note, reviewer.username AS reviewer_username, r.reviewed_at, r.created_at
             FROM auth_user_transfer_requests r
             INNER JOIN auth_users u ON u.id = r.user_id
             LEFT JOIN auth_users old_parent ON old_parent.id = r.from_parent_id
             LEFT JOIN auth_users new_parent ON new_parent.id = r.requested_parent_id
             INNER JOIN auth_users requester ON requester.id = r.requested_by_user_id
             LEFT JOIN auth_users reviewer ON reviewer.id = r.reviewed_by_user_id
             {$where} ORDER BY r.id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'total' => $total];
    }

    /** @return array<string,mixed> */
    public function review(int $requestId, int $adminId, string $decision, string $note, string $ip, string $agent): array
    {
        if (!in_array($decision, ['APPROVED','REJECTED'], true) || mb_strlen($note) > 500) {
            throw new InvalidArgumentException('审批操作或审批备注无效。');
        }
        return Db::transaction(function () use ($requestId, $adminId, $decision, $note, $ip, $agent): array {
            $rows = Db::query('SELECT * FROM auth_user_transfer_requests WHERE id = ? FOR UPDATE', [$requestId]);
            $request = $rows[0] ?? null;
            if (!is_array($request) || $request['status'] !== 'PENDING') throw new RuntimeException('申请不存在或已经处理。');
            $users = Db::query('SELECT id, role, parent_id, status FROM auth_users WHERE id = ? FOR UPDATE', [(int)$request['user_id']]);
            $user = $users[0] ?? null;
            if (!is_array($user) || $user['role'] !== 'USER' || $user['status'] !== 'ACTIVE') throw new RuntimeException('用户状态已变化，无法审批。');
            if (($user['parent_id'] === null ? null : (int)$user['parent_id']) !== ($request['from_parent_id'] === null ? null : (int)$request['from_parent_id'])) {
                throw new RuntimeException('用户当前归属已变化，请重新提交申请。');
            }
            if ($decision === 'APPROVED' && $request['requested_parent_id'] !== null) {
                $this->assertActiveTarget((int)$request['requested_parent_id']);
            }
            if ($decision === 'APPROVED') {
                Db::execute('UPDATE auth_users SET parent_id = ?, assigned_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?', [
                    $request['requested_parent_id'] !== null ? (int)$request['requested_parent_id'] : null, (int)$request['user_id'],
                ]);
            }
            Db::execute(
                'UPDATE auth_user_transfer_requests SET status = ?, reviewed_by_user_id = ?, review_note = ?, reviewed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$decision, $adminId, $note !== '' ? $note : null, $requestId],
            );
            $this->audit($adminId, 'USER_TRANSFER_' . $decision, (int)$request['user_id'], $ip, $agent, ['request_id' => $requestId]);
            return $this->findRequest($requestId);
        });
    }

    /** @return array<string,mixed> */
    public function setStatus(int $userId, string $status, int $adminId, string $ip, string $agent): array
    {
        if (!in_array($status, ['ACTIVE','DISABLED','LOCKED'], true)) throw new InvalidArgumentException('账号状态无效。');
        return Db::transaction(function () use ($userId, $status, $adminId, $ip, $agent): array {
            $rows = Db::query('SELECT id, username, role, status FROM auth_users WHERE id = ? FOR UPDATE', [$userId]);
            $user = $rows[0] ?? null;
            if (!is_array($user) || $user['role'] === 'ADMIN') throw new RuntimeException('用户不存在或不能通过此入口修改管理员账号。');
            Db::execute('UPDATE auth_users SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$status, $userId]);
            if ($status !== 'ACTIVE') {
                Db::execute('UPDATE auth_user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
            }
            $this->audit($adminId, 'USER_STATUS_CHANGED', $userId, $ip, $agent, ['from' => $user['status'], 'to' => $status]);
            $updated = Db::query('SELECT id, username, role, status, updated_at FROM auth_users WHERE id = ? LIMIT 1', [$userId]);
            return $updated[0] ?? [];
        });
    }

    private function assertActiveTarget(int $targetId): void
    {
        $rows = Db::query("SELECT id FROM auth_users WHERE id = ? AND role IN ('AGENT','SUB_AGENT') AND status = 'ACTIVE' LIMIT 1", [$targetId]);
        if (!isset($rows[0])) throw new InvalidArgumentException('归属目标必须是启用状态的代理或下级代理。');
    }

    /** @return array<string,mixed> */
    private function findRequest(int $id): array
    {
        $rows = Db::query(
            "SELECT r.id, r.user_id, u.username, r.from_parent_id, old_parent.username AS from_parent_username,
                    r.requested_parent_id, new_parent.username AS requested_parent_username, r.request_reason,
                    r.status, r.review_note, r.reviewed_at, r.created_at
             FROM auth_user_transfer_requests r INNER JOIN auth_users u ON u.id = r.user_id
             LEFT JOIN auth_users old_parent ON old_parent.id = r.from_parent_id
             LEFT JOIN auth_users new_parent ON new_parent.id = r.requested_parent_id
             WHERE r.id = ? LIMIT 1", [$id],
        );
        return $rows[0] ?? [];
    }

    /** @param array<string,mixed> $meta */
    private function audit(int $actorId, string $event, int $targetId, string $ip, string $agent, array $meta = []): void
    {
        Db::execute(
            'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, ip_address, user_agent, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$actorId, $event, 'USER', (string)$targetId, $ip ?: null, mb_substr($agent, 0, 255, 'UTF-8'), json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
    }
}
