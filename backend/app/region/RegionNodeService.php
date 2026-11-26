<?php
declare(strict_types=1);

namespace app\region;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class RegionNodeService
{
    /** @return list<array<string,mixed>> */
    public function list(): array
    {
        return Db::query(
            'SELECT id, region_code, region_name, api_base_url, traffic_weight, status, description, created_at, updated_at
             FROM auth_region_nodes ORDER BY traffic_weight DESC, id ASC',
        );
    }

    /** @param array<string,mixed> $input */
    public function create(array $input, int $actorId): array
    {
        [$code, $name, $url, $weight, $status, $description] = $this->validate($input);
        try {
            Db::execute(
                'INSERT INTO auth_region_nodes
                 (region_code, region_name, api_base_url, traffic_weight, status, description, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [$code, $name, $url, $weight, $status, $description],
            );
        } catch (\think\exception\DbException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                throw new InvalidArgumentException('区域代码已存在。');
            }
            throw $exception;
        }
        $id = (int)Db::getLastInsID();
        $this->audit($actorId, 'REGION_NODE_CREATED', $id);
        return $this->find($id);
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input, int $actorId): array
    {
        if ($id < 1) throw new InvalidArgumentException('区域 ID 无效。');
        [$code, $name, $url, $weight, $status, $description] = $this->validate($input);
        $exists = Db::query('SELECT id FROM auth_region_nodes WHERE id = ? LIMIT 1', [$id]);
        if (!isset($exists[0])) throw new RuntimeException('区域节点不存在。');
        try {
            Db::execute(
                'UPDATE auth_region_nodes SET region_code = ?, region_name = ?, api_base_url = ?,
                 traffic_weight = ?, status = ?, description = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$code, $name, $url, $weight, $status, $description, $id],
            );
        } catch (\think\exception\DbException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                throw new InvalidArgumentException('区域代码已存在。');
            }
            throw $exception;
        }
        $this->audit($actorId, 'REGION_NODE_UPDATED', $id);
        return $this->find($id);
    }

    private function validate(array $input): array
    {
        $code = strtoupper(trim((string)($input['region_code'] ?? '')));
        $name = trim((string)($input['region_name'] ?? ''));
        $url = rtrim(trim((string)($input['api_base_url'] ?? '')), '/');
        $weight = (int)($input['traffic_weight'] ?? 0);
        $status = strtoupper(trim((string)($input['status'] ?? 'DISABLED')));
        $description = mb_substr(trim((string)($input['description'] ?? '')), 0, 255, 'UTF-8');
        if (!preg_match('/^[A-Z0-9_-]{2,32}$/', $code)) throw new InvalidArgumentException('区域代码仅允许 2–32 位大写字母、数字、下划线或短横线。');
        if ($name === '' || mb_strlen($name, 'UTF-8') > 80) throw new InvalidArgumentException('区域名称不能为空且不能超过 80 字。');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || strlen($url) > 500) {
            throw new InvalidArgumentException('区域 API 地址必须是无凭据、无查询参数的 HTTPS 基础地址。');
        }
        if ($weight < 0 || $weight > 10000) throw new InvalidArgumentException('流量权重必须在 0–10000 之间。');
        if (!in_array($status, ['ACTIVE', 'DISABLED'], true)) throw new InvalidArgumentException('区域状态无效。');
        return [$code, $name, $url, $weight, $status, $description];
    }

    private function find(int $id): array
    {
        $rows = Db::query('SELECT id, region_code, region_name, api_base_url, traffic_weight, status, description, created_at, updated_at FROM auth_region_nodes WHERE id = ? LIMIT 1', [$id]);
        return $rows[0] ?? [];
    }

    private function audit(int $actorId, string $event, int $id): void
    {
        Db::execute(
            'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$actorId, $event, 'REGION_NODE', (string)$id],
        );
    }
}
