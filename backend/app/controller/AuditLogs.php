<?php
declare(strict_types=1);

namespace app\controller;

use app\audit\AuditLogService;
use app\auth\AuthContext;
use think\Request;
use think\response\Json;

final class AuditLogs
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        $data = (new AuditLogService())->list(
            (int)$request->get('page', 1),
            (int)$request->get('page_size', 20),
            trim((string)$request->get('event_type', '')),
            trim((string)$request->get('target_type', '')),
            trim((string)$request->get('keyword', '')),
        );
        return json(['success' => true, 'data' => $data]);
    }
}
