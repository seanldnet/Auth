<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\http\RequestPayload;
use app\license\LicenseManagementService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class LicenseManagement
{
    public function index(Request $request, string $softwareId): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        try {
            $result = (new LicenseManagementService())->list(
                (int)$softwareId,
                (int)$request->get('page', 1),
                (int)$request->get('page_size', 20),
                (string)$request->get('status', ''),
                trim((string)$request->get('keyword', '')),
            );
            return json(['success' => true, 'data' => $result]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function generate(Request $request, string $softwareId): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            $result = (new LicenseManagementService())->generate(
                (int)$softwareId, (int)($payload['plan_id'] ?? 0), (int)($payload['quantity'] ?? 0),
                (int)$user['id'], (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function import(Request $request, string $softwareId): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            $result = (new LicenseManagementService())->import(
                (int)$softwareId, (int)($payload['plan_id'] ?? 0),
                is_array($payload['license_keys'] ?? null) ? $payload['license_keys'] : [],
                (int)$user['id'], (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function export(Request $request, string $softwareId)
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        try {
            $csv = (new LicenseManagementService())->exportCsv(
                (int)$softwareId,
                strtoupper((string)$request->get('status', '')),
                trim((string)$request->get('keyword', '')),
            );
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="licenses.csv"',
                'Cache-Control' => 'no-store',
            ]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function unbindDevice(Request $request, string $licenseId): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        try {
            $license = (new LicenseManagementService())->unbindDevice(
                (int)$licenseId, (int)$user['id'], (string)$request->ip(),
                (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $license]);
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), '不存在') ? 404 : 422;
            return json(['success' => false, 'message' => $exception->getMessage()], $status);
        }
    }

    public function status(Request $request, string $licenseId): Json
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            $license = (new LicenseManagementService())->setStatus(
                (int)$licenseId, (string)($payload['status'] ?? ''), (int)$user['id'],
                (string)$request->ip(), (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $license]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), '不存在') ? 404 : 422;
            return json(['success' => false, 'message' => $exception->getMessage()], $status);
        }
    }
}
