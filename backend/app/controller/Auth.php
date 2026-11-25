<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\auth\AuthSessionService;
use app\http\RequestPayload;
use RuntimeException;
use think\Request;
use think\response\Json;

final class Auth
{
    public function login(Request $request): Json
    {
        try {
            $payload = RequestPayload::all($request);
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

    public function changePassword(Request $request): Json
    {
        $user = AuthContext::get($request);
        if ($user === null) {
            return json(['success' => false, 'message' => '请先登录'], 401);
        }

        $payload = RequestPayload::all($request);
        try {
            (new AuthSessionService())->changePassword(
                (int) $user['id'],
                (int) $user['session_id'],
                (string) ($payload['current_password'] ?? ''),
                (string) ($payload['new_password'] ?? ''),
            );

            return json(['success' => true, 'message' => '密码已更新，请重新登录其他设备']);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
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
