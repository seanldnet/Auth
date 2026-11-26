<?php
declare(strict_types=1);

namespace app\license;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class LicenseManagementService
{
    /**
     * @return array{licenses:list<array{id:int,license_no:string,license_key:string}>,quantity:int}
     */
    public function generate(int $softwareId, int $planId, int $quantity, int $actorId, string $ip, string $userAgent): array
    {
        if ($softwareId < 1 || $planId < 1 || $quantity < 1 || $quantity > 500) {
            throw new InvalidArgumentException('软件、套餐或生成数量无效；单次最多生成 500 个授权码。');
        }
        $softwareRows = Db::query('SELECT id, status FROM auth_software_apps WHERE id = ? LIMIT 1', [$softwareId]);
        $software = $softwareRows[0] ?? null;
        if (!is_array($software) || $software['status'] !== 'ACTIVE') {
            throw new RuntimeException('软件不存在或当前未启用。');
        }
        $planRows = Db::query(
            "SELECT id, software_id, duration_unit, duration_value, status
             FROM auth_license_plans WHERE id = ? AND software_id = ? LIMIT 1",
            [$planId, $softwareId],
        );
        $plan = $planRows[0] ?? null;
        if (!is_array($plan) || $plan['status'] !== 'ACTIVE') {
            throw new RuntimeException('套餐不存在、未启用或不属于所选软件。');
        }

        $created = [];
        Db::transaction(function () use ($softwareId, $planId, $quantity, $actorId, $ip, $userAgent, &$created): void {
            for ($i = 0; $i < $quantity; $i++) {
                $plainKey = $this->newLicenseKey();
                $licenseNo = 'LIC-' . strtoupper(bin2hex(random_bytes(6)));
                Db::execute(
                    'INSERT INTO auth_licenses
                     (license_no, license_key_hash, license_key_hint, software_id, plan_id,
                      issued_by_user_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                    [
                        $licenseNo, hash('sha256', $plainKey),
                        substr($plainKey, 0, 4) . '...' . substr($plainKey, -4),
                        $softwareId, $planId, $actorId, 'GENERATED',
                    ],
                );
                $created[] = [
                    'id' => (int)Db::getLastInsID(),
                    'license_no' => $licenseNo,
                    'license_key' => $plainKey,
                ];
            }
            foreach ($created as $license) {
                $this->event((int)$license['id'], $actorId, 'LICENSE_GENERATED', null, [
                    'software_id' => $softwareId, 'plan_id' => $planId, 'status' => 'GENERATED',
                ], $ip, $userAgent);
            }
        });

        return ['licenses' => $created, 'quantity' => count($created)];
    }

    /** @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(int $softwareId, int $page, int $pageSize, string $status = '', string $keyword = ''): array
    {
        if ($softwareId < 1) {
            throw new InvalidArgumentException('必须指定软件。');
        }
        $software = Db::query('SELECT id FROM auth_software_apps WHERE id = ? LIMIT 1', [$softwareId]);
        if (!isset($software[0])) {
            throw new RuntimeException('软件不存在。');
        }
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $where = ['l.software_id = ?'];
        $params = [$softwareId];
        if ($status !== '') {
            if (!in_array($status, ['GENERATED','ACTIVE','FROZEN','VOID','EXPIRED'], true)) {
                throw new InvalidArgumentException('授权状态筛选值无效。');
            }
            $where[] = 'l.status = ?';
            $params[] = $status;
        }
        if ($keyword !== '') {
            $where[] = '(l.license_no LIKE ? OR l.license_key_hint LIKE ?)';
            $params[] = '%' . mb_substr($keyword, 0, 80) . '%';
            $params[] = '%' . mb_substr($keyword, 0, 80) . '%';
        }
        $clause = implode(' AND ', $where);
        $count = Db::query("SELECT COUNT(*) AS aggregate FROM auth_licenses l WHERE {$clause}", $params);
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT l.id, l.license_no, l.license_key_hint, l.software_id, l.plan_id,
                    p.name AS plan_name, l.owner_user_id, l.status, l.device_label,
                    l.activated_at, l.expires_at, l.machine_change_count, l.last_verified_at,
                    l.created_at, l.updated_at
             FROM auth_licenses l
             INNER JOIN auth_license_plans p ON p.id = l.plan_id
             WHERE {$clause}
             ORDER BY l.id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'page' => $page, 'page_size' => $pageSize, 'total' => $total];
    }

    /** @return array<string,mixed> */
    public function setStatus(int $licenseId, string $nextStatus, int $actorId, string $ip, string $userAgent): array
    {
        if (!in_array($nextStatus, ['FROZEN','ACTIVE','VOID'], true)) {
            throw new InvalidArgumentException('只允许冻结、恢复或作废授权。');
        }
        return Db::transaction(function () use ($licenseId, $nextStatus, $actorId, $ip, $userAgent): array {
            $rows = Db::query('SELECT * FROM auth_licenses WHERE id = ? FOR UPDATE', [$licenseId]);
            $license = $rows[0] ?? null;
            if (!is_array($license)) {
                throw new RuntimeException('授权记录不存在。');
            }
            $before = ['status' => $license['status'], 'frozen_from_status' => $license['frozen_from_status']];
            $current = (string)$license['status'];
            if ($current === 'VOID' || $current === 'EXPIRED') {
                throw new RuntimeException('已作废或已过期的授权不能再修改状态。');
            }
            if ($nextStatus === 'FROZEN') {
                if ($current === 'FROZEN') {
                    throw new RuntimeException('该授权已经被冻结。');
                }
                Db::execute(
                    'UPDATE auth_licenses SET frozen_from_status = ?, status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                    [$current, 'FROZEN', $licenseId],
                );
            } elseif ($nextStatus === 'ACTIVE') {
                if ($current !== 'FROZEN') {
                    throw new RuntimeException('只有被冻结的授权才能恢复。');
                }
                $restoreStatus = in_array($license['frozen_from_status'], ['GENERATED','ACTIVE'], true)
                    ? (string)$license['frozen_from_status'] : 'ACTIVE';
                Db::execute(
                    'UPDATE auth_licenses SET status = ?, frozen_from_status = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                    [$restoreStatus, $licenseId],
                );
            } else {
                Db::execute(
                    'UPDATE auth_licenses SET status = ?, frozen_from_status = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                    ['VOID', $licenseId],
                );
            }
            $updatedRows = Db::query('SELECT status, frozen_from_status FROM auth_licenses WHERE id = ? LIMIT 1', [$licenseId]);
            $after = $updatedRows[0] ?? ['status' => $nextStatus];
            $this->event($licenseId, $actorId, 'LICENSE_' . $nextStatus, $before, $after, $ip, $userAgent);
            return $this->publicLicense($licenseId);
        });
    }

    /** @return array<string,mixed> */
    private function publicLicense(int $id): array
    {
        $rows = Db::query(
            'SELECT id, license_no, license_key_hint, software_id, plan_id, owner_user_id,
                    status, device_label, activated_at, expires_at, machine_change_count,
                    last_verified_at, created_at, updated_at
             FROM auth_licenses WHERE id = ? LIMIT 1',
            [$id],
        );
        return $rows[0] ?? [];
    }

    private function newLicenseKey(): string
    {
        $hex = strtoupper(bin2hex(random_bytes(16)));
        return implode('-', str_split($hex, 8));
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    private function event(int $licenseId, int $actorId, string $type, ?array $before, array $after, string $ip, string $userAgent): void
    {
        Db::execute(
            'INSERT INTO auth_license_events
             (license_id, actor_user_id, event_type, before_state, after_state, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $licenseId, $actorId, $type,
                $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $ip !== '' ? $ip : null, mb_substr($userAgent, 0, 255, 'UTF-8'),
            ],
        );
    }
}
