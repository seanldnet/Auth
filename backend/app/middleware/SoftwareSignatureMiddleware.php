<?php
declare(strict_types=1);

namespace app\middleware;

use app\security\SoftwareSignatureVerifier;
use Closure;
use think\Request;
use think\Response;
use think\facade\Db;

final class SoftwareSignatureMiddleware
{
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $appId = trim((string)$request->header('x-app-id', ''));
        $keyId = trim((string)$request->header('x-key-id', ''));
        $timestamp = trim((string)$request->header('x-timestamp', ''));
        $nonce = trim((string)$request->header('x-nonce', ''));
        $signatureHeader = trim((string)$request->header('x-signature', ''));

        if (!preg_match('/^app_[a-f0-9]{24}$/i', $appId)
            || !preg_match('/^key_[a-f0-9]{16}$/', $keyId)
            || !preg_match('/^[0-9]{10,11}$/', $timestamp)
            || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)
            || $signatureHeader === ''
        ) {
            return json(['success' => false, 'message' => '软件签名请求头无效'], 401);
        }
        if (abs(time() - (int)$timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            return json(['success' => false, 'message' => '请求时间戳已过期'], 401);
        }
        $signature = base64_decode($signatureHeader, true);
        if (!is_string($signature) || strlen($signature) < 256 || strlen($signature) > 1024) {
            return json(['success' => false, 'message' => '请求签名格式无效'], 401);
        }

        $softwareRows = Db::query(
            "SELECT id FROM auth_software_apps WHERE app_id = ? AND status = 'ACTIVE' LIMIT 1",
            [$appId],
        );
        $software = $softwareRows[0] ?? null;
        if (!is_array($software)) return json(['success' => false, 'message' => '软件身份不存在或已停用'], 401);

        $keyRows = Db::query(
            "SELECT public_key_pem FROM auth_software_public_keys
             WHERE software_id = ? AND key_id = ? AND status = 'ACTIVE' LIMIT 1",
            [(int)$software['id'], $keyId],
        );
        $key = $keyRows[0] ?? null;
        if (!is_array($key)) return json(['success' => false, 'message' => '软件签名密钥不存在或已撤销'], 401);

        $body = (string)$request->getInput();
        $canonical = SoftwareSignatureVerifier::canonicalRequest(
            (string)$request->method(), (string)$request->pathinfo(), $timestamp, $nonce, $body,
        );
        if (!SoftwareSignatureVerifier::verify((string)$key['public_key_pem'], $canonical, $signature)) {
            return json(['success' => false, 'message' => '软件请求签名验证失败'], 401);
        }

        try {
            Db::execute(
                'INSERT INTO auth_software_request_nonces (app_id, key_id, nonce, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [$appId, $keyId, $nonce],
            );
        } catch (\think\exception\DbException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                return json(['success' => false, 'message' => '检测到重复请求（Nonce 重放）'], 409);
            }
            throw $exception;
        }

        if (random_int(1, 100) === 1) {
            Db::execute('DELETE FROM auth_software_request_nonces WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)');
        }

        return $next($request);
    }
}
