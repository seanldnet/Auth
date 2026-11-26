<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\region\RegionNodeService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class RegionNodes
{
    public function index(Request $request): Json
    {
        if (!$this->admin($request)) return json(['success' => false, 'message' => '需要管理员权限'], 403);
        return json(['success' => true, 'data' => (new RegionNodeService())->list()]);
    }

    public function create(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        try {
            $data = (new RegionNodeService())->create(RequestPayload::all($request), (int)$actor['id']);
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, string $regionId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') return json(['success' => false, 'message' => '需要管理员权限'], 403);
        try {
            $data = (new RegionNodeService())->update((int)$regionId, RequestPayload::all($request), (int)$actor['id']);
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    private function admin(Request $request): bool
    {
        $actor = AuthContext::get($request);
        return $actor !== null && ($actor['role'] ?? '') === 'ADMIN';
    }
}
