<?php
declare(strict_types=1);

namespace tests;

use app\security\SoftwareSignatureVerifier;
use phpseclib3\Crypt\RSA;
use PHPUnit\Framework\TestCase;

final class SoftwareSignatureVerifierTest extends TestCase
{
    public function testCanonicalRequestIncludesBodyDigestAndNormalizedMethod(): void
    {
        $canonical = SoftwareSignatureVerifier::canonicalRequest(
            'post', 'api/license/verify', '1795700000', 'nonce_1234567890123456', '{"app_id":"app_test"}',
        );
        self::assertStringStartsWith("POST\n/api/license/verify\n1795700000\nnonce_1234567890123456\n", $canonical);
        self::assertStringEndsWith(hash('sha256', '{"app_id":"app_test"}'), $canonical);
    }

    public function testRsaPssSignatureIsAcceptedAndTamperingIsRejected(): void
    {
        $private = RSA::createKey(3072)
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')
            ->withSaltLength(32);
        $publicPem = $private->getPublicKey()->toString('PKCS8');
        $message = "POST\n/api/license/verify\n1795700000\nnonce_1234567890123456\n" . hash('sha256', '{}');
        $signature = $private->sign($message);

        self::assertTrue(SoftwareSignatureVerifier::verify($publicPem, $message, $signature));
        self::assertFalse(SoftwareSignatureVerifier::verify($publicPem, $message . 'tampered', $signature));
    }
}
