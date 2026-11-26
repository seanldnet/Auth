<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\user\UserManagementService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class UserManagement
{
    public function register(Request $request): Json
    {
        $payload = RequestPayload::all($request);
        try {
            $data = (new UserManagementService())->register(
                (string)($payload['username'] ?? ''), (string)($payload['password'] ?? ''),
                trim((string)($payload['invite_code'] ?? '')), (string)$request->ip(),
                (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => '注册暂不可用，请稍后重试。'], 422);
        }
    }

    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        try {
            $data = (new UserManagementService())->listUsers(
                $actor, (int)$request->get('page', 1), (int)$request->get('page_size', 20),
                strtoupper((string)$request->get('role', '')), strtoupper((string)$request->get('status', '')),
                trim((string)$request->get('keyword', '')), (string)$request->get('pool', '') === '1',
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
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
            $data = (new UserManagementService())->createUser(
                $actor, (string)($payload['username'] ?? ''), strtoupper((string)($payload['role'] ?? 'USER')),
                (string)($payload['password'] ?? ''), (string)$request->ip(),
                (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 403);
        }
    }

    public function assign(Request $request, string $userId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            $data = (new UserManagementService())->assignPublicUser(
                (int)$userId, (int)($payload['agent_id'] ?? 0), (int)$actor['id'],
                (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
