<?php
declare(strict_types=1);

namespace app\middleware;

use app\auth\AuthContext;
use Closure;
use think\Request;
use think\Response;

final class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AuthContext::get($request);
        if ($user === null || ($user['role'] ?? '') !== 'ADMIN') {
            return json(['success' => false, 'message' => '需要管理员权限'], 403);
        }

        return $next($request);
    }
}
