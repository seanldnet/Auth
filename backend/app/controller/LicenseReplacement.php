<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\license\LicenseReplacementService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class LicenseReplacement
{
    public function replace(Request $request, string $licenseId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['ADMIN','AGENT','SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '当前账号没有更换授权的权限'], 403);
        }
        try {
            $data = (new LicenseReplacementService())->replace(
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
