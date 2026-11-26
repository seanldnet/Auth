<?php
declare(strict_types=1);

namespace app\notification;

use think\facade\Db;

final class ExpiryNotificationService
{
    public function dispatch(): int
    {
        $created = 0;
        foreach ([7, 3, 1] as $days) {
            $rows = Db::query(
                "SELECT id, license_no, expires_at, issued_by_user_id, owner_user_id
                 FROM auth_licenses
                 WHERE status = 'ACTIVE' AND expires_at IS NOT NULL
                   AND DATE(expires_at) = DATE(UTC_DATE() + INTERVAL {$days} DAY)",
            );
            foreach ($rows as $license) {
                $recipients = array_values(array_unique(array_filter([
                    (int)($license['issued_by_user_id'] ?? 0),
                    (int)($license['owner_user_id'] ?? 0),
                ], static fn(int $id): bool => $id > 0)));
                $expiry = (string)$license['expires_at'];
                foreach ($recipients as $userId) {
                    $eventKey = 'license-expiry:' . (int)$license['id'] . ':' . $expiry . ':' . $days;
                    $body = '授权编号 ' . (string)$license['license_no'] . ' 将于 ' . $expiry . ' 到期，请及时处理。';
                    $created += Db::execute(
                        'INSERT IGNORE INTO auth_notifications
                         (user_id, event_key, category, title, body, payload, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                        [$userId, $eventKey, 'LICENSE_EXPIRY', '授权即将到期（' . $days . ' 天）',
                         $body, json_encode([
                             'license_id' => (int)$license['id'],
                             'license_no' => (string)$license['license_no'],
                             'expires_at' => $expiry,
                             'days_before' => $days,
                         ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
                    );
                }
            }
        }
        return $created;
    }
}
