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
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['ADMIN','AGENT','SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '当前账号没有 API Key 管理权限'], 403);
        }
        return json(['success' => true, 'data' => (new ApiKeyService())->list($actor)]);
    }

    public function create(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['ADMIN','AGENT','SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '当前账号没有 API Key 管理权限'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            $data = (new ApiKeyService())->create(
                (int)$actor['id'],
                (string)($payload['name'] ?? ''),
                is_array($payload['scopes'] ?? null) ? $payload['scopes'] : [],
                is_array($payload['ip_allowlist'] ?? null) ? $payload['ip_allowlist'] : [],
                isset($payload['expires_at']) ? (string)$payload['expires_at'] : null,
            );
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function revoke(Request $request, string $keyId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['ADMIN','AGENT','SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '当前账号没有 API Key 管理权限'], 403);
        }
        try {
            (new ApiKeyService())->revoke($actor, (int)$keyId);
            return json(['success' => true]);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }
}
