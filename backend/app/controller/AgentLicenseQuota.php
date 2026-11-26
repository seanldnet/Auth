<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\license\AgentLicenseQuotaService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class AgentLicenseQuota
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) {
            return json(['success' => false, 'message' => '请先登录'], 401);
        }
        try {
            $data = (new AgentLicenseQuotaService())->list(
                $actor,
                (int) $request->get('page', 1),
                (int) $request->get('page_size', 20),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 403);
        }
    }

    public function allocate(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            $result = (new AgentLicenseQuotaService())->allocate(
                (int) ($payload['user_id'] ?? 0),
                (int) ($payload['software_id'] ?? 0),
                (int) ($payload['plan_id'] ?? 0),
                (int) ($payload['quantity'] ?? 0),
                (int) $actor['id'],
                (string) ($request->header('idempotency-key') ?: ($payload['idempotency_key'] ?? '')),
                (string) ($payload['description'] ?? ''),
            );
            return json(['success' => true, 'data' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 409);
        }
    }
}
