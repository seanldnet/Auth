<?php
declare(strict_types=1);

namespace app\user;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class UserManagementService
{
    /** @return array<string,mixed> */
    public function register(string $username, string $password, string $inviteCode, string $ip, string $agent): array
    {
        $username = $this->validateUsername($username);
        $this->validatePassword($password);
        $parent = null;
        if ($inviteCode !== '') {
            $rows = Db::query(
                "SELECT id, role, status FROM auth_users
                 WHERE invite_code = ? AND role IN ('AGENT','SUB_AGENT') LIMIT 1",
                [$inviteCode],
            );
            $candidate = $rows[0] ?? null;
            if (!is_array($candidate) || $candidate['status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('邀请码无效或邀请人账号不可用。');
            }
            $parent = (int)$candidate['id'];
        }
        $hash = $this->passwordHash($password);
        try {
            Db::transaction(function () use ($username, $hash, $parent, $ip, $agent): void {
                Db::execute(
                    'INSERT INTO auth_users
                     (username, password_hash, role, parent_id, created_by_user_id, assigned_at,
                      status, must_change_password, password_updated_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                    [$username, $hash, 'USER', $parent, $parent, $parent !== null ? gmdate('Y-m-d H:i:s') : null, 'ACTIVE'],
                );
                $id = (int)Db::getLastInsID();
                $this->audit($parent, 'USER_REGISTERED', $id, $ip, $agent, [
                    'registration_type' => $parent === null ? 'PUBLIC_POOL' : 'INVITE',
                ]);
            });
        } catch (\think\exception\DbException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                throw new InvalidArgumentException('用户名已被使用。');
            }
            throw $exception;
        }
        return ['message' => '注册成功，请登录。'];
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,page_size:int} */
    public function listUsers(array $actor, int $page, int $pageSize, string $role, string $status, string $keyword, bool $poolOnly = false): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $actorRole = (string)$actor['role'];
        if (!in_array($actorRole, ['ADMIN','AGENT','SUB_AGENT'], true)) {
            throw new RuntimeException('当前账号没有用户管理权限。');
        }
        $where = [];
        $params = [];
        if ($actorRole === 'ADMIN') {
            if ($poolOnly) $where[] = 'u.parent_id IS NULL AND u.role = \'USER\'';
        } elseif ($actorRole === 'AGENT') {
            $descendants = Db::query(
                'WITH RECURSIVE descendants AS (
                    SELECT id FROM auth_users WHERE parent_id = ?
                    UNION ALL
                    SELECT child.id FROM auth_users child INNER JOIN descendants d ON child.parent_id = d.id
                ) SELECT id FROM descendants',
                [(int)$actor['id']],
            );
            $ids = array_map(static fn(array $row): int => (int)$row['id'], $descendants);
            if ($ids === []) {
                $where[] = '1 = 0';
            } else {
                $where[] = 'u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                array_push($params, ...$ids);
            }
            if ($poolOnly) throw new RuntimeException('只有管理员可以查看公共用户池。');
        } else {
            $where[] = 'u.parent_id = ?';
            $params[] = (int)$actor['id'];
        }
        if ($role !== '') {
            $allowed = $actorRole === 'ADMIN' ? ['ADMIN','AGENT','SUB_AGENT','USER']
                : ($actorRole === 'AGENT' ? ['SUB_AGENT','USER'] : ['USER']);
            if (!in_array($role, $allowed, true)) throw new InvalidArgumentException('不能查看该角色类型。');
            $where[] = 'u.role = ?'; $params[] = $role;
        }
        if ($status !== '') {
            if (!in_array($status, ['ACTIVE','DISABLED','LOCKED'], true)) throw new InvalidArgumentException('账号状态无效。');
            $where[] = 'u.status = ?'; $params[] = $status;
        }
        if ($keyword !== '') {
            $where[] = 'u.username LIKE ?'; $params[] = '%' . addcslashes(mb_substr($keyword, 0, 64), '%_\\') . '%';
        }
        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $count = Db::query("SELECT COUNT(*) AS aggregate FROM auth_users u {$clause}", $params);
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT u.id, u.username, u.role, u.parent_id, parent.username AS parent_username,
                    u.invite_code, u.status, u.must_change_password, u.last_login_at, u.created_at
             FROM auth_users u LEFT JOIN auth_users parent ON parent.id = u.parent_id
             {$clause} ORDER BY u.id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /** @return array<string,mixed> */
    public function createUser(array $actor, string $username, string $role, string $password, string $ip, string $agent): array
    {
        $username = $this->validateUsername($username);
        $actorRole = (string)$actor['role'];
        $allowed = match ($actorRole) {
            'ADMIN' => ['AGENT','USER'],
            'AGENT' => ['SUB_AGENT','USER'],
            'SUB_AGENT' => ['USER'],
            default => [],
        };
        if (!in_array($role, $allowed, true)) throw new InvalidArgumentException('当前账号不能创建该角色。');
        $generated = $password === '';
        if ($generated) $password = bin2hex(random_bytes(8)) . 'aA1!';
        $this->validatePassword($password);
        $parentId = $actorRole === 'ADMIN' ? null : (int)$actor['id'];
        $inviteCode = in_array($role, ['AGENT','SUB_AGENT'], true) ? strtoupper(bin2hex(random_bytes(6))) : null;
        $hash = $this->passwordHash($password);
        try {
            Db::transaction(function () use ($username, $role, $parentId, $inviteCode, $hash, $generated, $actor, $ip, $agent): void {
                Db::execute(
                    'INSERT INTO auth_users
                     (username, password_hash, role, parent_id, invite_code, created_by_user_id, assigned_at,
                      status, must_change_password, password_updated_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                    [$username, $hash, $role, $parentId, $inviteCode, (int)$actor['id'],
                     $parentId !== null ? gmdate('Y-m-d H:i:s') : null, 'ACTIVE', $generated ? 1 : 0],
                );
                $id = (int)Db::getLastInsID();
                $this->audit((int)$actor['id'], 'USER_CREATED', $id, $ip, $agent, ['role' => $role]);
            });
        } catch (\think\exception\DbException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) throw new InvalidArgumentException('用户名已被使用。');
            throw $exception;
        }
        $rows = Db::query('SELECT id, username, role, parent_id, invite_code, status, must_change_password, created_at FROM auth_users WHERE username = ? LIMIT 1', [$username]);
        $result = $rows[0] ?? [];
        if ($generated) $result['initial_password'] = $password;
        return $result;
    }

    /** @return array<string,mixed> */
    public function assignPublicUser(int $userId, int $agentId, int $actorId, string $ip, string $agent): array
    {
        return Db::transaction(function () use ($userId, $agentId, $actorId, $ip, $agent): array {
            $users = Db::query('SELECT id, role, parent_id FROM auth_users WHERE id = ? FOR UPDATE', [$userId]);
            $user = $users[0] ?? null;
            $agents = Db::query("SELECT id, role, status FROM auth_users WHERE id = ? FOR UPDATE", [$agentId]);
            $target = $agents[0] ?? null;
            if (!is_array($user) || $user['role'] !== 'USER' || $user['parent_id'] !== null) {
                throw new RuntimeException('该用户不属于公共用户池或不存在。');
            }
            if (!is_array($target) || !in_array($target['role'], ['AGENT','SUB_AGENT'], true) || $target['status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('分配目标必须是启用状态的代理或下级代理。');
            }
            Db::execute('UPDATE auth_users SET parent_id = ?, assigned_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?', [$agentId, $userId]);
            $this->audit($actorId, 'PUBLIC_USER_ASSIGNED', $userId, $ip, $agent, ['assigned_to' => $agentId]);
            $rows = Db::query('SELECT id, username, role, parent_id, status, assigned_at FROM auth_users WHERE id = ? LIMIT 1', [$userId]);
            return $rows[0] ?? [];
        });
    }

    private function validateUsername(string $username): string
    {
        $username = trim($username);
        if (!preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_.-]{2,63}$/', $username)) {
            throw new InvalidArgumentException('用户名必须为 3–64 位字母、数字、点、下划线或连字符。');
        }
        return $username;
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 1024) {
            throw new InvalidArgumentException('密码长度必须为 12 至 1024 个字符。');
        }
    }

    private function passwordHash(string $password): string
    {
        $algorithm = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($password, $algorithm);
        if (!is_string($hash)) throw new RuntimeException('密码安全哈希生成失败。');
        return $hash;
    }

    /** @param array<string,mixed> $metadata */
    private function audit(?int $actorId, string $event, int $targetId, string $ip, string $agent, array $metadata = []): void
    {
        Db::execute(
            'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, ip_address, user_agent, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$actorId, $event, 'USER', (string)$targetId, $ip ?: null, mb_substr($agent, 0, 255, 'UTF-8'),
             json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
    }
}
