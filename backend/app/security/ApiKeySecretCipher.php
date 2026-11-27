<?php
declare(strict_types=1);

namespace app\security;

use RuntimeException;

final class ApiKeySecretCipher
{
    public static function encrypt(string $secret): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('API Key Secret 加密失败。');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $payload = base64_decode($encoded, true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('API Key Secret 密文无效。');
        }
        $secret = openssl_decrypt(
            substr($payload, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA,
            substr($payload, 0, 12), substr($payload, 12, 16),
        );
        if ($secret === false) {
            throw new RuntimeException('API Key Secret 解密失败。');
        }
        return $secret;
    }

    private static function key(): string
    {
        $master = (string)(getenv('APP_KEY') ?: '');
        if (strlen($master) < 32) {
            throw new RuntimeException('启用签名 API Key 前，APP_KEY 必须至少为 32 个字符。');
        }
        return hash('sha256', $master, true);
    }
}
