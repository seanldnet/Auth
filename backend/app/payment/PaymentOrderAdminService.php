<?php
declare(strict_types=1);

namespace app\payment;

use InvalidArgumentException;
use think\facade\Db;

final class PaymentOrderAdminService
{
    /** @return array{items:list<array<string,mixed>>,total:int,page:int,page_size:int} */
    public function list(int $page = 1, int $pageSize = 20, string $status = '', string $keyword = ''): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $where = [];
        $params = [];
        if ($status !== '') {
            if (!in_array($status, ['CREATING','PENDING','PAID','EXPIRED','CANCELLED','REVIEW'], true)) {
                throw new InvalidArgumentException('订单状态筛选值无效。');
            }
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($keyword !== '') {
            $where[] = '(p.order_no LIKE ? OR u.username LIKE ? OR p.provider_trade_id LIKE ?)';
            $term = '%' . mb_substr($keyword, 0, 80) . '%';
            array_push($params, $term, $term, $term);
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = Db::query(
            "SELECT COUNT(*) AS aggregate FROM auth_payment_orders p INNER JOIN auth_users u ON u.id = p.user_id{$clause}",
            $params,
        );
        $total = (int)($count[0]['aggregate'] ?? 0);
        $offset = ($page - 1) * $pageSize;
        $items = Db::query(
            "SELECT p.order_no, p.user_id, u.username, p.channel_code, p.currency, p.amount,
                    p.provider_currency, p.provider_amount, p.provider_trade_id, p.status,
                    p.created_at, p.expires_at, p.paid_at
             FROM auth_payment_orders p INNER JOIN auth_users u ON u.id = p.user_id
             {$clause} ORDER BY p.id DESC LIMIT {$pageSize} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
