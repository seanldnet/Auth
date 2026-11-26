<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\license\LicenseRenewalService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class LicenseRenewal
{
    public function renew(Request $request, string $licenseId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['ADMIN','AGENT','SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '当前账号没有授权续费权限'], 403);
        }
        try {
            $data = (new LicenseRenewalService())->renew(
                (int)$licenseId,
                $actor,
                (string)$request->header('idempotency-key', ''),
                (string)$request->ip(),
                (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $data]);
        } catch (InvalidArgumentException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 409);
        }
    }
}
