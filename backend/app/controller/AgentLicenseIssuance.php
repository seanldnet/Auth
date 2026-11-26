<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\license\AgentLicenseIssuanceService;
use app\license\LicenseManagementService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class AgentLicenseIssuance
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['ADMIN', 'AGENT', 'SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '当前账号没有授权查询权限。'], 403);
        }
        $page = (int)$request->get('page', 1);
        $pageSize = (int)$request->get('page_size', 20);
        $status = strtoupper((string)$request->get('status', ''));
        $keyword = trim((string)$request->get('keyword', ''));
        if (($actor['role'] ?? '') === 'ADMIN') {
            $softwareId = (int)$request->get('software_id', 0);
            if ($softwareId < 1) return json(['success' => false, 'message' => '管理员查询授权必须指定 software_id。'], 422);
            $result = (new LicenseManagementService())->list($softwareId, $page, $pageSize, $status, $keyword);
        } else {
            $result = (new AgentLicenseIssuanceService())->listForAgent((int)$actor['id'], $page, $pageSize, $status, $keyword);
        }
        return json(['success' => true, 'data' => $result]);
    }

    public function create(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['ADMIN', 'AGENT', 'SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '当前账号没有授权生成权限。'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            if (($actor['role'] ?? '') === 'ADMIN') {
                $result = (new LicenseManagementService())->generate(
                    (int)($payload['software_id'] ?? 0),
                    (int)($payload['plan_id'] ?? 0),
                    (int)($payload['quantity'] ?? 0),
                    (int)$actor['id'],
                    (string)$request->ip(),
                    (string)$request->header('user-agent', ''),
                );
            } else {
                $result = (new AgentLicenseIssuanceService())->issue(
                    (int)$actor['id'],
                    (int)($payload['software_id'] ?? 0),
                    (int)($payload['plan_id'] ?? 0),
                    (int)($payload['quantity'] ?? 0),
                    (string)$request->header('Idempotency-Key', ''),
                    (string)$request->ip(),
                    (string)$request->header('user-agent', ''),
                );
            }
            return json(['success' => true, 'data' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
