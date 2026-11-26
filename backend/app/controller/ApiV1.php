<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\license\AgentLicenseIssuanceService;
use app\license\AgentLicenseQuotaService;
use app\license\LicenseManagementService;
use app\user\UserManagementService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class ApiV1
{
    public function licenses(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => 'API Key 身份缺失'], 401);
        $softwareId = (int)$request->get('software_id', 0);
        try {
            if (strtoupper($request->method()) === 'GET') {
                if ($softwareId < 1 && ($actor['role'] ?? '') === 'ADMIN') {
                    return json(['success' => false, 'message' => '管理员查询授权时必须指定 software_id'], 422);
                }
                $page = (int)$request->get('page', 1);
                $pageSize = (int)$request->get('page_size', 20);
                $status = strtoupper((string)$request->get('status', ''));
                $keyword = trim((string)$request->get('keyword', ''));
                $data = ($actor['role'] ?? '') === 'ADMIN'
                    ? (new LicenseManagementService())->list($softwareId, $page, $pageSize, $status, $keyword)
                    : (new AgentLicenseIssuanceService())->listForAgent((int)$actor['id'], $page, $pageSize, $status, $keyword);
                return json(['success' => true, 'data' => $data]);
            }

            $payload = RequestPayload::all($request);
            $softwareId = (int)($payload['software_id'] ?? 0);
            $planId = (int)($payload['plan_id'] ?? 0);
            $quantity = (int)($payload['quantity'] ?? 0);
            if (($actor['role'] ?? '') === 'ADMIN') {
                $data = (new LicenseManagementService())->generate(
                    $softwareId, $planId, $quantity, (int)$actor['id'],
                    (string)$request->ip(), (string)$request->header('user-agent', ''),
                );
            } else {
                $data = (new AgentLicenseIssuanceService())->issue(
                    (int)$actor['id'], $softwareId, $planId, $quantity,
                    (string)$request->header('idempotency-key', ''),
                    (string)$request->ip(), (string)$request->header('user-agent', ''),
                );
            }
            return json(['success' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 409);
        }
    }

    public function users(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => 'API Key 身份缺失'], 401);
        try {
            $data = (new UserManagementService())->listUsers(
                $actor, (int)$request->get('page', 1), (int)$request->get('page_size', 20),
                strtoupper((string)$request->get('role', '')), strtoupper((string)$request->get('status', '')),
                trim((string)$request->get('keyword', '')), false,
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function quotas(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => 'API Key 身份缺失'], 401);
        try {
            $data = (new AgentLicenseQuotaService())->list(
                $actor, (int)$request->get('page', 1), (int)$request->get('page_size', 20),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }
}
