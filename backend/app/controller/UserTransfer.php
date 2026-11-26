<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\user\UserTransferService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class UserTransfer
{
    public function request(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        $payload = RequestPayload::all($request);
        try {
            if (($actor['role'] ?? '') !== 'USER') throw new RuntimeException('只有普通用户可以从此入口提交归属申请。');
            $data = (new UserTransferService())->requestByUser(
                (int)$actor['id'], (string)($payload['invite_code'] ?? ''), trim((string)($payload['reason'] ?? '')),
                (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function adminRequest(Request $request, string $userId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        $payload = RequestPayload::all($request);
        try {
            $target = $payload['target_parent_id'] ?? null;
            $data = (new UserTransferService())->requestByAdmin(
                (int)$userId, $target === null || $target === '' ? null : (int)$target,
                (int)$actor['id'], trim((string)($payload['reason'] ?? '')),
                (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        try {
            $data = (new UserTransferService())->listRequests(
                (int)$request->get('page', 1), (int)$request->get('page_size', 20),
                strtoupper((string)$request->get('status', 'PENDING')),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function review(Request $request, string $requestId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        $payload = RequestPayload::all($request);
        try {
            $data = (new UserTransferService())->review(
                (int)$requestId, (int)$actor['id'], strtoupper((string)($payload['decision'] ?? '')),
                trim((string)($payload['note'] ?? '')), (string)$request->ip(),
                (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function status(Request $request, string $userId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        $payload = RequestPayload::all($request);
        try {
            $data = (new UserTransferService())->setStatus(
                (int)$userId, strtoupper((string)($payload['status'] ?? '')),
                (int)$actor['id'], (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
