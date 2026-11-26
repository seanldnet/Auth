<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\payment\EpayAdapter;
use app\payment\EpusdtAdapter;
use app\payment\GmpayAdapter;
use app\payment\PaymentChannelRegistry;
use app\payment\PaymentCurrency;
use app\payment\PaymentOrderService;
use RuntimeException;
use think\Request;
use think\facade\Db;
use think\response\Json;

final class PaymentOrder
{

    public function channels(): Json
    {
        $registry = new PaymentChannelRegistry();
        $registry->register(new EpusdtAdapter());
        $registry->register(new GmpayAdapter());
        $registry->register(new EpayAdapter());

        $rows = Db::name('payment_channels')->where('enabled', 1)->select()->toArray();
        $channels = [];
        foreach ($rows as $row) {
            $code = strtoupper((string) $row['channel_code']);
            try {
                $adapter = $registry->get($code);
            } catch (RuntimeException) {
                continue;
            }

            $configuredCurrencies = json_decode((string) $row['allowed_currencies'], true);
            if (!is_array($configuredCurrencies)) {
                continue;
            }
            $currencies = array_values(array_intersect(
                ['CNY', 'USDT'],
                array_map('strtoupper', $configuredCurrencies),
                array_map(static fn (PaymentCurrency $currency): string => $currency->value, $adapter->supportedCurrencies()),
            ));
            if ($currencies === []) {
                continue;
            }

            $channels[] = [
                'channel_code' => $code,
                'display_name' => (string) $row['display_name'],
                'allowed_currencies' => $currencies,
                'cashier_mode' => true,
            ];
        }

        return json(['success' => true, 'data' => $channels]);
    }

    public function index(Request $request): Json
    {
        $user = AuthContext::get($request);
        if ($user === null) {
            return json(['success' => false, 'message' => '请先登录'], 401);
        }

        $page = max(1, min(100000, (int) $request->get('page', 1)));
        $pageSize = max(1, min(50, (int) $request->get('page_size', 20)));
        $userId = (int) $user['id'];

        $total = Db::name('payment_orders')->where('user_id', $userId)->count();
        $rows = Db::name('payment_orders')
            ->where('user_id', $userId)
            ->field('order_no, channel_code, currency, amount, status, created_at, expires_at, paid_at')
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $orders = [];
        foreach ($rows as $row) {
            $currency = PaymentCurrency::from((string) $row['currency']);
            $orders[] = [
                'order_no' => (string) $row['order_no'],
                'channel_code' => (string) $row['channel_code'],
                'currency' => $currency->value,
                'amount' => (string) $row['amount'],
                'display_amount' => $currency->displayAmount((string) $row['amount']),
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
                'expires_at' => $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
                'paid_at' => $row['paid_at'] !== null ? (string) $row['paid_at'] : null,
            ];
        }

        return json([
            'success' => true,
            'data' => [
                'items' => $orders,
                'total' => (int) $total,
                'page' => $page,
                'page_size' => $pageSize,
            ],
        ]);
    }

    public function create(Request $request): Json
    {
        $user = AuthContext::get($request);
        if ($user === null) {
            return json(['success' => false, 'message' => '请先登录'], 401);
        }

        try {
            $payload = RequestPayload::all($request);
            $currency = PaymentCurrency::from(strtoupper((string) ($payload['currency'] ?? '')));
            $idempotencyKey = (string) (
                $request->header('idempotency-key')
                ?: ($payload['idempotency_key'] ?? '')
            );

            $registry = new PaymentChannelRegistry();
            $registry->register(new EpusdtAdapter());
            $registry->register(new GmpayAdapter());
            $registry->register(new EpayAdapter());

            $data = (new PaymentOrderService($registry))->create(
                (int) $user['id'],
                (string) ($payload['channel_code'] ?? ''),
                $currency,
                (string) ($payload['amount'] ?? ''),
                $idempotencyKey,
                [
                    'subject' => (string) ($payload['subject'] ?? 'Account top-up'),
                    'payment_type' => isset($payload['payment_type']) ? (string) $payload['payment_type'] : null,
                    'token' => isset($payload['token']) ? (string) $payload['token'] : null,
                    'network' => isset($payload['network']) ? (string) $payload['network'] : null,
                ],
            );

            unset($data['checkout_token']);
            return json(['success' => true, 'data' => $data]);
        } catch (\ValueError|RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
