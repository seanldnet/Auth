<?php
declare(strict_types=1);

namespace app\security;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

final class SoftwareSignatureVerifier
{
    public static function canonicalRequest(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return strtoupper(trim($method)) . "\n"
            . '/' . trim($path, '/') . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . hash('sha256', $body);
    }

    public static function verify(string $publicKeyPem, string $message, string $signature): bool
    {
        try {
            $key = PublicKeyLoader::load($publicKeyPem);
            return $key
                ->withHash('sha256')
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withMGFHash('sha256')
                ->withSaltLength(32)
                ->verify($message, $signature);
        } catch (Throwable) {
            return false;
        }
    }
}
