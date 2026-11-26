<?php
declare(strict_types=1);

namespace app\notification;

use think\facade\Db;

final class NotificationService
{
    /** @return array{items:list<array<string,mixed>>,total:int,unread_count:int,page:int,page_size:int} */
    public function list(int $userId, int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $count = Db::query('SELECT COUNT(*) AS aggregate FROM auth_notifications WHERE user_id = ?', [$userId]);
        $unread = Db::query('SELECT COUNT(*) AS aggregate FROM auth_notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT id, category, title, body, payload, read_at, created_at
             FROM auth_notifications WHERE user_id = ?
             ORDER BY id DESC LIMIT {$pageSize} OFFSET {$offset}",
            [$userId],
        );
        return [
            'items' => $items, 'total' => $total,
            'unread_count' => (int)($unread[0]['aggregate'] ?? 0),
            'page' => $page, 'page_size' => $pageSize,
        ];
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $affected = Db::execute(
            'UPDATE auth_notifications SET read_at = COALESCE(read_at, UTC_TIMESTAMP())
             WHERE id = ? AND user_id = ?',
            [$notificationId, $userId],
        );
        if ($affected < 1) {
            $exists = Db::query('SELECT id FROM auth_notifications WHERE id = ? AND user_id = ? LIMIT 1', [$notificationId, $userId]);
            if ($exists === []) throw new \RuntimeException('通知不存在。');
        }
    }

    public function markAllRead(int $userId): int
    {
        return Db::execute(
            'UPDATE auth_notifications SET read_at = UTC_TIMESTAMP()
             WHERE user_id = ? AND read_at IS NULL',
            [$userId],
        );
    }
}
