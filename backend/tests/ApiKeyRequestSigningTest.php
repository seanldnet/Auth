<?php
declare(strict_types=1);

namespace tests;

use app\\security\\ApiKeyRequestSigner;
use app\\security\\ApiKeySecretCipher;
use PHPUnit\\Framework\\TestCase;
use RuntimeException;

final class ApiKeyRequestSigningTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_KEY=auth-api-key-test-master-secret-at-least-32-bytes');
    }

    protected function tearDown(): void
    {
        putenv('APP_KEY');
    }

    public function testSecretCipherRoundTripsAndRejectsTampering(): void
    {
        $secret = 'unit-test-signing-secret';
        $ciphertext = ApiKeySecretCipher::encrypt($secret);
        self::assertNotSame($secret, $ciphertext);
        self::assertSame($secret, ApiKeySecretCipher::decrypt($ciphertext));

        $raw = base64_decode($ciphertext, true);
        self::assertIsString($raw);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        $this->expectException(RuntimeException::class);
        ApiKeySecretCipher::decrypt(base64_encode($raw));
    }

    public function testCanonicalSignatureBindsMethodPathTimestampNonceAndBody(): void
    {
        $canonical = ApiKeyRequestSigner::canonicalRequest(
            'post', '/api/v1/licenses?ignored=1', '1795766400', 'nonce-1234567890', '{"software_id":7}',
        );
        $signature = ApiKeyRequestSigner::sign('secret', $canonical);
        self::assertTrue(ApiKeyRequestSigner::verify('secret', $canonical, $signature));
        self::assertFalse(ApiKeyRequestSigner::verify('other-secret', $canonical, $signature));
        self::assertFalse(ApiKeyRequestSigner::verify('secret', $canonical . 'x', $signature));
    }

    public function testQueryStringIsExcludedButPathIsBound(): void
    {
        $one = ApiKeyRequestSigner::canonicalRequest('GET', '/api/v1/users?page=1', '1', 'nonce-1234567890', '');
        $two = ApiKeyRequestSigner::canonicalRequest('GET', '/api/v1/users?page=2', '1', 'nonce-1234567890', '');
        $three = ApiKeyRequestSigner::canonicalRequest('GET', '/api/v1/licenses', '1', 'nonce-1234567890', '');
        self::assertSame($one, $two);
        self::assertNotSame($one, $three);
    }
}
