<?php
declare(strict_types=1);

namespace AuthPlatform\LicenseSdk\Tests;

use AuthPlatform\LicenseSdk\LicenseClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use phpseclib3\Crypt\RSA;

final class LicenseClientTest extends TestCase
{
    public function testCanonicalMessageUsesExactBodyHashAndUppercaseMethod(): void
    {
        $body = '{"app_id":"app_123"}';
        $canonical = LicenseClient::buildCanonicalMessage(
            'post',
            '/api/license/verify',
            '1795700000',
            '0123456789abcdef0123456789abcdef',
            $body,
        );

        self::assertStringStartsWith(
            "POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n",
            $canonical,
        );
        self::assertStringEndsWith(hash('sha256', $body), $canonical);
    }

    public function testRsaPssSignatureVerifiesAndTamperingFails(): void
    {
        $private = RSA::createKey(3072);
        $public = $private->getPublicKey();
        $message = 'signed request';
        $signature = $private
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')
            ->withSaltLength(32)
            ->sign($message);

        self::assertTrue($public
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')
            ->withSaltLength(32)
            ->verify($message, $signature));
        self::assertFalse($public
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')
            ->withSaltLength(32)
            ->verify($message . 'x', $signature));
    }

    public function testCanonicalPathRejectsQueryString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LicenseClient::buildCanonicalMessage('POST', '/api/license/verify?x=1', '1', '0123456789abcdef', '');
    }

    public function testClientRequiresHttpsAndValidIdentifiers(): void
    {
        $key = RSA::createKey(3072);
        $this->expectException(InvalidArgumentException::class);
        new LicenseClient('http://license.example.com', 'app_0123456789abcdef01234567', 'key_0123456789abcdef', $key->toString('PKCS8'));
    }
}
