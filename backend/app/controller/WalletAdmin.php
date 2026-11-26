<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\payment\WalletAdminService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class WalletAdmin
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        try {
            $data = (new WalletAdminService())->listUsers(
                (int)$request->get('page', 1), (int)$request->get('page_size', 20),
                trim((string)$request->get('keyword', '')), strtoupper((string)$request->get('role', '')),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function credit(Request $request, string $userId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        $payload = RequestPayload::all($request);
        try {
            $data = (new WalletAdminService())->credit(
                (int)$userId,
                (string)($payload['amount'] ?? ''),
                (string)$request->header('idempotency-key', $payload['idempotency_key'] ?? ''),
                (string)($payload['description'] ?? ''),
                (int)$actor['id'],
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 409);
        }
    }
}
