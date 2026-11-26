<?php
declare(strict_types=1);

namespace app\middleware;

use app\auth\AuthContext;
use app\auth\AuthSessionService;
use Closure;
use think\Request;
use think\Response;

final class AuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieToken = (string) $request->cookie('auth_session', '');
        $authorization = (string) $request->header('authorization', '');
        if ($cookieToken === '' && preg_match('/^Bearer\\s+ak_[a-f0-9]{64}$/i', $authorization)) {
            return (new ApiKeyMiddleware())->handle($request, $next);
        }
        $token = $cookieToken;

        if ($token === '' && preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
            $token = strtolower($matches[1]);
        }

        if ($token === '') {
            return json(['success' => false, 'message' => '请先登录'], 401);
        }

        if ($cookieToken !== '' && in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $origin = (string) $request->header('origin', '');
            if ($origin !== '') {
                $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
                $requestHost = strtolower((string) parse_url('http://' . $request->host(), PHP_URL_HOST));
                if ($originHost === '' || $requestHost === '' || !hash_equals($requestHost, $originHost)) {
                    return json(['success' => false, 'message' => '跨站请求已拒绝'], 403);
                }
            }
        }

        $user = (new AuthSessionService())->authenticate($token);
        if ($user === null) {
            return json(['success' => false, 'message' => '登录已过期，请重新登录'], 401);
        }

        AuthContext::set($request, $user);

        $path = trim((string) $request->pathinfo(), '/');
        if (!empty($user['must_change_password'])
            && !in_array($path, ['api/auth/me', 'api/auth/logout', 'api/auth/change-password'], true)
        ) {
            return json([
                'success' => false,
                'code' => 'PASSWORD_CHANGE_REQUIRED',
                'message' => '首次登录必须先修改密码',
            ], 403);
        }

        return $next($request);
    }
}
