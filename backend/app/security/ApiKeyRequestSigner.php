<?php
declare(strict_types=1);

namespace app\security;

final class ApiKeyRequestSigner
{
    public static function canonicalRequest(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return strtoupper(trim($method)) . "\n"
            . '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/') . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . hash('sha256', $body);
    }

    public static function sign(string $secret, string $canonicalRequest): string
    {
        return hash_hmac('sha256', $canonicalRequest, $secret);
    }

    public static function verify(string $secret, string $canonicalRequest, string $signature): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $signature)) return false;
        return hash_equals(self::sign($secret, $canonicalRequest), strtolower($signature));
    }
}
