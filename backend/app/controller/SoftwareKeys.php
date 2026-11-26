<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\security\SoftwareKeyService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class SoftwareKeys
{
    public function index(Request $request, string $softwareId): Json
    {
        if (!$this->isAdmin($request)) return json(['success' => false, 'message' => '需要管理员权限'], 403);
        try {
            return json(['success' => true, 'data' => (new SoftwareKeyService())->list((int)$softwareId)]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function add(Request $request, string $softwareId): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        $payload = RequestPayload::all($request);
        try {
            $data = (new SoftwareKeyService())->add((int)$softwareId, (string)($payload['public_key_pem'] ?? ''), (int)$user['id']);
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function revoke(Request $request, string $softwareId, string $keyId): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        try {
            $data = (new SoftwareKeyService())->revoke((int)$softwareId, $keyId, (int)$user['id']);
            return json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function isAdmin(Request $request): bool
    {
        $user = AuthContext::get($request);
        return $user !== null && ($user['role'] ?? '') === 'ADMIN';
    }
}
