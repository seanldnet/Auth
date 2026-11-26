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
            throw new RuntimeException('API key secret encryption failed.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $payload = base64_decode($encoded, true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Invalid encrypted API key secret.');
        }
        $secret = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16),
        );
        if ($secret === false) {
            throw new RuntimeException('API key secret decryption failed.');
        }
        return $secret;
    }

    private static function key(): string
    {
        $masterKey = (string)getenv('APP_KEY');
        if (strlen($masterKey) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters.');
        }
        return hash('sha256', $masterKey, true);
    }
}
