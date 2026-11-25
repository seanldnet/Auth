<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;

final class PaymentCredentialCipher
{
    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encrypt payment channel credentials.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $payload = base64_decode($encoded, true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Invalid encrypted payment credential format.');
        }

        $plaintext = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16),
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt payment channel credentials.');
        }

        return $plaintext;
    }

    private static function key(): string
    {
        $masterKey = (string) getenv('APP_KEY');
        if (strlen($masterKey) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters.');
        }

        return hash('sha256', $masterKey, true);
    }
}
