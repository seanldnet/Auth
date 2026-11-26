<?php
declare(strict_types=1);

namespace app\software;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class SoftwareCatalogService
{
    /** @return list<array<string,mixed>> */
    public function listSoftware(bool $includeArchived = false): array
    {
        $where = $includeArchived ? '' : "WHERE a.status <> 'ARCHIVED'";
        return Db::query(
            "SELECT a.id, a.app_id, a.name, a.description, a.status,
                    a.renew_consumes_quota, a.offline_days, a.created_at, a.updated_at,
                    (SELECT COUNT(*) FROM auth_license_plans p WHERE p.software_id = a.id) AS plan_count
             FROM auth_software_apps a {$where} ORDER BY a.id DESC"
        );
    }

    /** @return array<string,mixed> */
    public function createSoftware(array $input, int $actorId): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $offlineDays = filter_var($input['offline_days'] ?? 0, FILTER_VALIDATE_INT);
        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('软件名称必填且不能超过 120 个字符，描述不能超过 1000 个字符。');
        }
        if ($offlineDays === false || $offlineDays < 0 || $offlineDays > 90) {
            throw new InvalidArgumentException('离线授权天数必须为 0 至 90 天。');
        }

        $appId = 'app_' . bin2hex(random_bytes(12));
        $appSecret = bin2hex(random_bytes(32));
        Db::execute(
            'INSERT INTO auth_software_apps
                (app_id, app_secret_hash, name, description, status, renew_consumes_quota,
                 offline_days, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [
                $appId, hash('sha256', $appSecret), $name,
                $description !== '' ? $description : null, 'ACTIVE',
                !empty($input['renew_consumes_quota']) ? 1 : 0, $offlineDays, $actorId,
            ],
        );
        $id = (int)Db::getLastInsID();
        $this->audit($actorId, 'SOFTWARE_CREATED', 'SOFTWARE', (string)$id, ['app_id' => $appId]);

        return [
            'software' => $this->findSoftware($id),
            'app_secret' => $appSecret,
        ];
    }

    /** @return array<string,mixed> */
    public function updateSoftware(int $id, array $input, int $actorId): array
    {
        $current = $this->findSoftware($id);
        if ($current['status'] === 'ARCHIVED') {
            throw new RuntimeException('已归档的软件不能修改。');
        }
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $offlineDays = filter_var($input['offline_days'] ?? 0, FILTER_VALIDATE_INT);
        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('软件名称必填且不能超过 120 个字符，描述不能超过 1000 个字符。');
        }
        if ($offlineDays === false || $offlineDays < 0 || $offlineDays > 90) {
            throw new InvalidArgumentException('离线授权天数必须为 0 至 90 天。');
        }
        Db::execute(
            'UPDATE auth_software_apps SET name = ?, description = ?, renew_consumes_quota = ?,
             offline_days = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$name, $description !== '' ? $description : null,
             !empty($input['renew_consumes_quota']) ? 1 : 0, $offlineDays, $id],
        );
        $this->audit($actorId, 'SOFTWARE_UPDATED', 'SOFTWARE', (string)$id);
        return $this->findSoftware($id);
    }

    /** @return array<string,mixed> */
    public function setSoftwareStatus(int $id, string $status, int $actorId): array
    {
        $this->findSoftware($id);
        if (!in_array($status, ['ACTIVE', 'DISABLED', 'ARCHIVED'], true)) {
            throw new InvalidArgumentException('无效的软件状态。');
        }
        Db::execute(
            'UPDATE auth_software_apps SET status = ?, archived_at = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, $status === 'ARCHIVED' ? gmdate('Y-m-d H:i:s') : null, $id],
        );
        $this->audit($actorId, 'SOFTWARE_STATUS_CHANGED', 'SOFTWARE', (string)$id, ['status' => $status]);
        return $this->findSoftware($id);
    }

    /** @return list<array<string,mixed>> */
    public function listPlans(int $softwareId): array
    {
        $this->findSoftware($softwareId);
        return Db::query(
            'SELECT id, software_id, name, plan_code, duration_unit, duration_value,
                    CAST(price AS CHAR) AS price, currency, status, description, created_at, updated_at
             FROM auth_license_plans WHERE software_id = ? ORDER BY id DESC',
            [$softwareId],
        );
    }

    /** @return array<string,mixed> */
    public function createPlan(int $softwareId, array $input, int $actorId): array
    {
        $software = $this->findSoftware($softwareId);
        if ($software['status'] !== 'ACTIVE') {
            throw new RuntimeException('只有启用状态的软件才能新增套餐。');
        }
        $data = $this->validatePlan($input);
        Db::execute(
            'INSERT INTO auth_license_plans
             (software_id, name, plan_code, duration_unit, duration_value, price, currency, status,
              description, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$softwareId, $data['name'], $data['plan_code'], $data['duration_unit'],
             $data['duration_value'], $data['price'], $data['currency'], $data['status'],
             $data['description'], $actorId],
        );
        $id = (int)Db::getLastInsID();
        $this->audit($actorId, 'LICENSE_PLAN_CREATED', 'LICENSE_PLAN', (string)$id, ['software_id' => $softwareId]);
        return $this->findPlan($softwareId, $id);
    }

    /** @return array<string,mixed> */
    public function updatePlan(int $softwareId, int $planId, array $input, int $actorId): array
    {
        $this->findPlan($softwareId, $planId);
        $data = $this->validatePlan($input);
        Db::execute(
            'UPDATE auth_license_plans SET name = ?, plan_code = ?, duration_unit = ?, duration_value = ?,
             price = ?, currency = ?, status = ?, description = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND software_id = ?',
            [$data['name'], $data['plan_code'], $data['duration_unit'], $data['duration_value'],
             $data['price'], $data['currency'], $data['status'], $data['description'], $planId, $softwareId],
        );
        $this->audit($actorId, 'LICENSE_PLAN_UPDATED', 'LICENSE_PLAN', (string)$planId, ['software_id' => $softwareId]);
        return $this->findPlan($softwareId, $planId);
    }

    /** @return array<string,mixed> */
    private function validatePlan(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $code = strtoupper(trim((string)($input['plan_code'] ?? '')));
        $unit = strtoupper(trim((string)($input['duration_unit'] ?? '')));
        $currency = strtoupper(trim((string)($input['currency'] ?? '')));
        $status = strtoupper(trim((string)($input['status'] ?? 'ACTIVE')));
        $duration = $unit === 'PERMANENT' ? null : filter_var($input['duration_value'] ?? null, FILTER_VALIDATE_INT);
        $price = trim((string)($input['price'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100 || !preg_match('/^[A-Z0-9_-]{2,32}$/', $code)) {
            throw new InvalidArgumentException('套餐名称必填；套餐编码只能包含 2 至 32 位大写字母、数字、下划线或连字符。');
        }
        if (!in_array($unit, ['DAY','MONTH','YEAR','PERMANENT'], true)
            || ($unit !== 'PERMANENT' && ($duration === false || $duration < 1 || $duration > 1200))) {
            throw new InvalidArgumentException('套餐时长配置无效。');
        }
        if (!in_array($currency, ['CNY','USDT'], true) || !in_array($status, ['ACTIVE','DISABLED'], true)) {
            throw new InvalidArgumentException('套餐币种或状态无效。');
        }
        if (!preg_match('/^(?:0|[1-9]\d{0,11})(?:\.\d{1,8})?$/', $price) || (float)$price < 0) {
            throw new InvalidArgumentException('套餐价格必须为非负十进制数，最多 8 位小数。');
        }
        if (mb_strlen($description) > 500) {
            throw new InvalidArgumentException('套餐描述不能超过 500 个字符。');
        }
        return [
            'name' => $name, 'plan_code' => $code, 'duration_unit' => $unit,
            'duration_value' => $duration, 'price' => $price, 'currency' => $currency,
            'status' => $status, 'description' => $description !== '' ? $description : null,
        ];
    }

    /** @return array<string,mixed> */
    private function findSoftware(int $id): array
    {
        $rows = Db::query('SELECT * FROM auth_software_apps WHERE id = ? LIMIT 1', [$id]);
        if (!isset($rows[0])) {
            throw new RuntimeException('软件不存在。');
        }
        return $rows[0];
    }

    /** @return array<string,mixed> */
    private function findPlan(int $softwareId, int $planId): array
    {
        $rows = Db::query(
            'SELECT id, software_id, name, plan_code, duration_unit, duration_value,
             CAST(price AS CHAR) AS price, currency, status, description, created_at, updated_at
             FROM auth_license_plans WHERE id = ? AND software_id = ? LIMIT 1',
            [$planId, $softwareId],
        );
        if (!isset($rows[0])) {
            throw new RuntimeException('套餐不存在或不属于指定软件。');
        }
        return $rows[0];
    }

    private function audit(int $actorId, string $event, string $targetType, string $targetId, array $metadata = []): void
    {
        Db::execute(
            'INSERT INTO auth_audit_logs (actor_user_id, event_type, target_type, target_id, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$actorId, $event, $targetType, $targetId,
             $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
    }
}
