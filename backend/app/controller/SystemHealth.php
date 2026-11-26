<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use think\facade\Db;
use think\Request;
use think\response\Json;

final class SystemHealth
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || ($actor['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }
        $database = 'ok';
        try {
            Db::query('SELECT 1 AS healthy');
        } catch (\Throwable) {
            $database = 'error';
        }
        $healthy = $database === 'ok';
        return json([
            'success' => true,
            'data' => [
                'overall' => $healthy ? 'ok' : 'degraded',
                'api' => 'ok',
                'database' => $database,
                'checked_at' => gmdate('c'),
            ],
        ]);
    }
}
