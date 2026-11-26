<?php
declare(strict_types=1);

namespace app\license;

use InvalidArgumentException;
use RuntimeException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

final class OfflineLicenseToken
{
    public static function encode(string $payloadJson, string $signature): string
    {
        if ($signature === '') throw new InvalidArgumentException('离线授权签名不能为空。');
        return self::base64UrlEncode($payloadJson) . '.' . self::base64UrlEncode($signature);
    }

    /**
     * @return array<string,mixed>
     */
    public static function verify(
        string $token,
        string $publicKeyPem,
        string $expectedAppId,
        string $expectedFingerprintHash,
        string $expectedKeyId,
        ?int $now = null,
    ): array {
        $parts = explode('.', $token);
        if (count($parts) !== 2) throw new InvalidArgumentException('离线授权凭证格式无效。');
        $payloadJson = self::base64UrlDecode($parts[0]);
        $signature = self::base64UrlDecode($parts[1]);
        if (strlen($signature) < 384 || strlen($signature) > 1024) {
            throw new InvalidArgumentException('离线授权签名长度无效。');
        }
        try {
            $payload = json_decode($payloadJson, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('离线授权载荷不是有效 JSON。', 0, $exception);
        }
        if (!is_array($payload) || ($payload['version'] ?? null) !== 1
            || ($payload['algorithm'] ?? null) !== 'RSA-PSS-SHA256'
            || !is_string($payload['key_id'] ?? null)
            || !is_string($payload['app_id'] ?? null)
            || !is_string($payload['device_fingerprint_hash'] ?? null)
            || !is_int($payload['issued_at'] ?? null)
            || !is_int($payload['not_before'] ?? null)
            || !is_int($payload['expires_at'] ?? null)) {
            throw new InvalidArgumentException('离线授权载荷字段不完整或版本不受支持。');
        }
        if (!hash_equals($expectedAppId, $payload['app_id'])
            || !hash_equals($expectedFingerprintHash, $payload['device_fingerprint_hash'])
            || !hash_equals($expectedKeyId, $payload['key_id'])) {
            throw new RuntimeException('离线授权凭证与当前软件或设备不匹配。');
        }
        $now ??= time();
        if ($payload['issued_at'] > $now || $payload['not_before'] > $now
            || $payload['expires_at'] <= $now || $payload['expires_at'] <= $payload['not_before']
            || $payload['expires_at'] - $payload['issued_at'] > 90 * 86400) {
            throw new RuntimeException('离线授权凭证尚未生效或已经过期。');
        }
        try {
            $publicKey = PublicKeyLoader::load($publicKeyPem)
                ->withHash('sha256')
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withMGFHash('sha256')
                ->withSaltLength(32);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('离线授权平台公钥无法解析。', 0, $exception);
        }
        if (!$publicKey->verify($payloadJson, $signature)) {
            throw new RuntimeException('离线授权签名验证失败。');
        }
        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException('离线授权凭证 Base64URL 编码无效。');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if (!is_string($decoded)) throw new InvalidArgumentException('离线授权凭证 Base64URL 解码失败。');
        return $decoded;
    }
}
