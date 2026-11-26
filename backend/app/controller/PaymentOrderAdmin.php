<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\payment\PaymentOrderAdminService;
use InvalidArgumentException;
use think\Request;
use think\response\Json;

final class PaymentOrderAdmin
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        try {
            $data = (new PaymentOrderAdminService())->list(
                (int)$request->get('page', 1), (int)$request->get('page_size', 20),
                strtoupper((string)$request->get('status', '')), trim((string)$request->get('keyword', '')),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
