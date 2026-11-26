<?php
declare(strict_types=1);

namespace app\audit;

use RuntimeException;
use think\facade\Db;

final class AuditLogService
{
    /** @return array{items:list<array<string,mixed>>,total:int,page:int,page_size:int} */
    public function list(int $page, int $pageSize, string $eventType = '', string $targetType = '', string $keyword = ''): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $where = [];
        $params = [];
        if ($eventType !== '') {
            $where[] = 'l.event_type = ?';
            $params[] = mb_substr($eventType, 0, 64);
        }
        if ($targetType !== '') {
            $where[] = 'l.target_type = ?';
            $params[] = mb_substr($targetType, 0, 64);
        }
        if ($keyword !== '') {
            $where[] = '(u.username LIKE ? OR l.target_id LIKE ? OR l.event_type LIKE ?)';
            $term = '%' . mb_substr($keyword, 0, 80) . '%';
            array_push($params, $term, $term, $term);
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = Db::query(
            "SELECT COUNT(*) AS aggregate FROM auth_audit_logs l LEFT JOIN auth_users u ON u.id = l.actor_user_id{$clause}",
            $params,
        );
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT l.id, l.actor_user_id, u.username AS actor_username, l.event_type,
                    l.target_type, l.target_id, l.ip_address, l.metadata, l.created_at
             FROM auth_audit_logs l
             LEFT JOIN auth_users u ON u.id = l.actor_user_id
             {$clause}
             ORDER BY l.id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
