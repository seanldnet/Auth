<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\user\UserManagementService;
use RuntimeException;
use think\Request;
use think\response\Json;

final class ApiKeyBusinessApi
{
    public function users(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !$this->allows($actor, 'user.read')) {
            return json(['success' => false, 'message' => 'API Key 无权执行该操作'], 403);
        }
        try {
            $data = (new UserManagementService())->listUsers(
                $actor, (int)$request->get('page', 1), (int)$request->get('page_size', 20),
                strtoupper((string)$request->get('role', '')), strtoupper((string)$request->get('status', '')),
                trim((string)$request->get('keyword', '')),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 403);
        } catch (\InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function subAgents(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !$this->allows($actor, 'sub_agent.read')) {
            return json(['success' => false, 'message' => 'API Key 无权执行该操作'], 403);
        }
        if (!in_array((string)($actor['role'] ?? ''), ['ADMIN', 'AGENT'], true)) {
            return json(['success' => false, 'message' => '当前角色没有下级代理查询权限'], 403);
        }
        try {
            $data = (new UserManagementService())->listUsers(
                $actor, (int)$request->get('page', 1), (int)$request->get('page_size', 20),
                'SUB_AGENT', strtoupper((string)$request->get('status', '')),
                trim((string)$request->get('keyword', '')),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 403);
        } catch (\InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    /** @param array<string,mixed> $actor */
    private function allows(array $actor, string $permission): bool
    {
        if (($actor['auth_type'] ?? '') !== 'API_KEY') return false;
        $permissions = $actor['api_key_permissions'] ?? [];
        return is_array($permissions) && (in_array('*', $permissions, true) || in_array($permission, $permissions, true));
    }
}
