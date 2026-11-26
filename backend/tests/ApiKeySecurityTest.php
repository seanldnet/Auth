<?php
declare(strict_types=1);

namespace tests;

use app\security\ApiKeyRequestSigner;
use app\security\ApiKeySecretCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApiKeySecurityTest extends TestCase
{
    public function testEncryptedSecretRoundTrips(): void
    {
        $previous = getenv('APP_KEY');
        putenv('APP_KEY=api-key-unit-test-master-key-with-at-least-32-characters');
        try {
            $secret = 'a-random-api-key-secret';
            $encrypted = ApiKeySecretCipher::encrypt($secret);
            self::assertNotSame($secret, $encrypted);
            self::assertSame($secret, ApiKeySecretCipher::decrypt($encrypted));
        } finally {
            $previous === false ? putenv('APP_KEY') : putenv('APP_KEY=' . $previous);
        }
    }

    public function testTamperedSecretCiphertextIsRejected(): void
    {
        $previous = getenv('APP_KEY');
        putenv('APP_KEY=api-key-unit-test-master-key-with-at-least-32-characters');
        try {
            $encrypted = ApiKeySecretCipher::encrypt('secret');
            $bytes = base64_decode($encrypted, true);
            self::assertIsString($bytes);
            $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 1);
            $this->expectException(RuntimeException::class);
            ApiKeySecretCipher::decrypt(base64_encode($bytes));
        } finally {
            $previous === false ? putenv('APP_KEY') : putenv('APP_KEY=' . $previous);
        }
    }

    public function testRequestSignatureBindsMethodPathTimestampNonceAndBody(): void
    {
        $canonical = ApiKeyRequestSigner::canonicalRequest('post', '/api/v1/licenses?ignored=1', '1700000000', 'nonce-1234567890', '{"a":1}');
        $signature = ApiKeyRequestSigner::sign('secret', $canonical);
        self::assertTrue(ApiKeyRequestSigner::verify('secret', $canonical, $signature));
        self::assertFalse(ApiKeyRequestSigner::verify('other-secret', $canonical, $signature));
        self::assertFalse(ApiKeyRequestSigner::verify('secret', $canonical . 'x', $signature));
    }
}
