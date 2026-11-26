<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\payment\PaymentChannelAdminService;
use RuntimeException;
use think\Request;
use think\response\Json;

final class PaymentChannelAdmin
{
    public function index(): Json
    {
        return json([
            'success' => true,
            'data' => (new PaymentChannelAdminService())->list(),
        ]);
    }

    public function save(Request $request, string $channelCode): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }

        try {
            $channel = (new PaymentChannelAdminService())->save(
                $channelCode,
                RequestPayload::all($request),
                (int) $user['id'],
                (string) $request->ip(),
                (string) $request->header('user-agent', ''),
            );

            return json(['success' => true, 'data' => $channel]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
