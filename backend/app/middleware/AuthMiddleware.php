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

        return $next($request);
    }
}
