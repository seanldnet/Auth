<?php
declare(strict_types=1);

namespace app\controller;

use app\payment\EpayAdapter;
use app\payment\EpusdtAdapter;
use app\payment\GmpayAdapter;
use app\payment\PaymentChannelRegistry;
use app\payment\PaymentOrderService;
use RuntimeException;
use think\response\Json;
use Throwable;

final class PaymentCashier
{
    public function index(string $orderNo, string $token): Json
    {
        try {
            $data = $this->service()->getCashier($orderNo, $token);
            unset($data['checkout_token']);

            return json(['success' => true, 'data' => $data]);
        } catch (Throwable $exception) {
            if (!$exception instanceof RuntimeException) {
                throw $exception;
            }

            return json(['success' => false, 'message' => '收银台订单不存在或访问凭据无效'], 404);
        }
    }

    public function status(string $orderNo, string $token): Json
    {
        return $this->index($orderNo, $token);
    }

    private function service(): PaymentOrderService
    {
        $registry = new PaymentChannelRegistry();
        $registry->register(new EpusdtAdapter());
        $registry->register(new GmpayAdapter());
        $registry->register(new EpayAdapter());

        return new PaymentOrderService($registry);
    }
}
