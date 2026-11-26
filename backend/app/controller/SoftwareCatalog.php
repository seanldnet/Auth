<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\software\SoftwareCatalogService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class SoftwareCatalog
{
    public function softwareIndex(): Json
    {
        return json(['success' => true, 'data' => (new SoftwareCatalogService())->listSoftware()]);
    }

    public function softwareCreate(Request $request): Json
    {
        return $this->respond($request, fn(array $user, array $payload) =>
            (new SoftwareCatalogService())->createSoftware($payload, (int)$user['id']), 201);
    }

    public function softwareUpdate(Request $request, string $softwareId): Json
    {
        return $this->respond($request, fn(array $user, array $payload) =>
            (new SoftwareCatalogService())->updateSoftware((int)$softwareId, $payload, (int)$user['id']));
    }

    public function softwareStatus(Request $request, string $softwareId): Json
    {
        return $this->respond($request, fn(array $user, array $payload) =>
            (new SoftwareCatalogService())->setSoftwareStatus(
                (int)$softwareId, (string)($payload['status'] ?? ''), (int)$user['id']));
    }

    public function planIndex(string $softwareId): Json
    {
        try {
            return json(['success' => true, 'data' => (new SoftwareCatalogService())->listPlans((int)$softwareId)]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function planCreate(Request $request, string $softwareId): Json
    {
        return $this->respond($request, fn(array $user, array $payload) =>
            (new SoftwareCatalogService())->createPlan((int)$softwareId, $payload, (int)$user['id']), 201);
    }

    public function planUpdate(Request $request, string $softwareId, string $planId): Json
    {
        return $this->respond($request, fn(array $user, array $payload) =>
            (new SoftwareCatalogService())->updatePlan((int)$softwareId, (int)$planId, $payload, (int)$user['id']));
    }

    private function respond(Request $request, callable $operation, int $successStatus = 200): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        try {
            $data = $operation($user, RequestPayload::all($request));
            return json(['success' => true, 'data' => $data], $successStatus);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), '不存在') ? 404 : 422;
            return json(['success' => false, 'message' => $exception->getMessage()], $status);
        }
    }
}
