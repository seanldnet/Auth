<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\security\ApiKeyService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class ApiKeys
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        try {
            return json(['success' => true, 'data' => (new ApiKeyService())->list(
                $actor, (int)$request->get('page', 1), (int)$request->get('page_size', 20),
            )]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 403);
        }
    }

    public function create(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        $payload = RequestPayload::all($request);
        try {
            $data = (new ApiKeyService())->create(
                $actor, (string)($payload['name'] ?? ''),
                is_array($payload['permissions'] ?? null) ? $payload['permissions'] : [],
                is_array($payload['ip_allowlist'] ?? null) ? $payload['ip_allowlist'] : [],
                trim((string)($payload['expires_at'] ?? '')),
                (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 403);
        }
    }

    public function revoke(Request $request, string $keyId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        try {
            return json(['success' => true, 'data' => (new ApiKeyService())->revoke(
                $actor, $keyId, (string)$request->ip(), (string)$request->header('user-agent', ''),
            )]);
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), '不存在') ? 404 : 403;
            return json(['success' => false, 'message' => $exception->getMessage()], $status);
        }
    }
}
