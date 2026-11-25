<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\auth\AuthSessionService;
use RuntimeException;
use think\Request;
use think\response\Json;

final class Auth
{
    public function login(Request $request): Json
    {
        try {
            $payload = $request->post();
            $result = (new AuthSessionService())->login(
                (string) ($payload['username'] ?? ''),
                (string) ($payload['password'] ?? ''),
                (string) $request->ip(),
                (string) $request->header('user-agent', ''),
            );

            setcookie('auth_session', $result['token'], [
                'expires' => time() + AuthSessionService::SESSION_TTL_SECONDS,
                'path' => '/',
                'secure' => $request->isSsl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            return json([
                'success' => true,
                'data' => [
                    'user' => $result['user'],
                    'expires_at' => $result['expires_at'],
                ],
            ]);
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), '次数过多') ? 429 : 401;
            return json(['success' => false, 'message' => $exception->getMessage()], $status);
        }
    }

    public function logout(Request $request): Json
    {
        $token = (string) $request->cookie('auth_session', '');
        (new AuthSessionService())->logout(
            $token,
            (string) $request->ip(),
            (string) $request->header('user-agent', ''),
        );

        setcookie('auth_session', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $request->isSsl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return json(['success' => true]);
    }

    public function me(Request $request): Json
    {
        $user = AuthContext::get($request);
        if ($user === null) {
            return json(['success' => false, 'message' => '请先登录'], 401);
        }

        unset($user['session_id']);
        return json(['success' => true, 'data' => ['user' => $user]]);
    }
}
