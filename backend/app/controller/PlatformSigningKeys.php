<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\security\PlatformSigningKeyAdminService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class PlatformSigningKeys
{
    public function index(Request $request): Json
    {
        try {
            return json(['success' => true, 'data' => (new PlatformSigningKeyAdminService())->index()]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public function create(Request $request): Json
    {
        $payload = RequestPayload::all($request);
        try {
            $actor = AuthContext::get($request) ?? throw new RuntimeException('未获取到管理员会话。');
            $data = (new PlatformSigningKeyAdminService())->create($payload, (int)$actor['id']);
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public function retire(Request $request, string $keyId): Json
    {
        $payload = RequestPayload::all($request);
        try {
            $actor = AuthContext::get($request) ?? throw new RuntimeException('未获取到管理员会话。');
            $data = (new PlatformSigningKeyAdminService())->retire(
                $keyId, (string)($payload['verify_until'] ?? ''), (int)$actor['id'],
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public function revoke(Request $request, string $keyId): Json
    {
        try {
            $actor = AuthContext::get($request) ?? throw new RuntimeException('未获取到管理员会话。');
            $data = (new PlatformSigningKeyAdminService())->revoke($keyId, (int)$actor['id']);
            return json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }
}
