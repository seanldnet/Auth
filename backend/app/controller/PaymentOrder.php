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
use think\response\Json;

final class PaymentOrder
{
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
