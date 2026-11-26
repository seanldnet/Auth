<?php
declare(strict_types=1);

namespace tests;

use app\license\OfflineLicenseToken;
use app\security\PlatformSignatureService;
use app\security\PlatformSigner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use phpseclib3\Crypt\RSA;
use RuntimeException;

final class OfflineLicenseTokenTest extends TestCase
{
    public function testSignedOfflineTokenVerifiesForExpectedSoftwareAndDevice(): void
    {
        $private = RSA::createKey(3072);
        $publicPem = $private->getPublicKey()->toString('PKCS8');
        $payload = [
            'version' => 1,
            'lease_id' => '123e4567-e89b-42d3-a456-426614174000',
            'app_id' => 'app_0123456789abcdef01234567',
            'license_no' => 'LIC-00000001',
            'device_fingerprint_hash' => hash('sha256', 'device-fingerprint'),
            'issued_at' => 1800000000,
            'not_before' => 1800000000,
            'expires_at' => 1800003600,
            'key_id' => 'pkey_0123456789abcdef',
            'algorithm' => 'RSA-PSS-SHA256',
        ];
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signer = new class($private) implements PlatformSigner {
            public function __construct(private readonly object $private) {}
            public function sign(string $keyReference, string $message): string
            {
                return $this->private->withHash('sha256')->withPadding(RSA::SIGNATURE_PSS)
                    ->withMGFHash('sha256')->withSaltLength(32)->sign($message);
            }
        };
        $signature = (new PlatformSignatureService($signer))->signAndVerify('kms/key/1', $publicPem, $payloadJson);
        $token = OfflineLicenseToken::encode($payloadJson, $signature);
        $verified = OfflineLicenseToken::verify(
            $token, $publicPem, $payload['app_id'], $payload['device_fingerprint_hash'], $payload['key_id'], 1800000100,
        );
        self::assertSame($payload['lease_id'], $verified['lease_id']);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $private = RSA::createKey(3072);
        $publicPem = $private->getPublicKey()->toString('PKCS8');
        $payload = json_encode([
            'version' => 1, 'app_id' => 'app_0123456789abcdef01234567',
            'device_fingerprint_hash' => hash('sha256', 'device-fingerprint'),
            'not_before' => 1800000000, 'expires_at' => 1800003600,
            'key_id' => 'pkey_0123456789abcdef', 'algorithm' => 'RSA-PSS-SHA256',
        ], JSON_THROW_ON_ERROR);
        $signature = $private->withHash('sha256')->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')->withSaltLength(32)->sign($payload);
        $token = OfflineLicenseToken::encode($payload, $signature);
        $tamperedPayload = str_replace('"expires_at":1800003600', '"expires_at":1800003601', $payload);
        $tampered = OfflineLicenseToken::encode($tamperedPayload, $signature);
        $this->expectException(RuntimeException::class);
        OfflineLicenseToken::verify(
            $tampered, $publicPem, 'app_0123456789abcdef01234567',
            hash('sha256', 'device-fingerprint'), 'pkey_0123456789abcdef', 1800000100,
        );
    }

    public function testWrongDeviceIsRejected(): void
    {
        $private = RSA::createKey(3072);
        $publicPem = $private->getPublicKey()->toString('PKCS8');
        $payload = json_encode([
            'version' => 1, 'app_id' => 'app_0123456789abcdef01234567',
            'device_fingerprint_hash' => hash('sha256', 'device-a'),
            'not_before' => 1800000000, 'expires_at' => 1800003600,
            'key_id' => 'pkey_0123456789abcdef', 'algorithm' => 'RSA-PSS-SHA256',
        ], JSON_THROW_ON_ERROR);
        $signature = $private->withHash('sha256')->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')->withSaltLength(32)->sign($payload);
        $token = OfflineLicenseToken::encode($payload, $signature);
        $this->expectException(RuntimeException::class);
        OfflineLicenseToken::verify(
            $token, $publicPem, 'app_0123456789abcdef01234567',
            hash('sha256', 'device-b'), 'pkey_0123456789abcdef', 1800000100,
        );
    }

    public function testMalformedTokenIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OfflineLicenseToken::verify('not-a-token', 'not-a-key', 'app_0123456789abcdef01234567', str_repeat('a', 64), 'pkey_0123456789abcdef');
    }
}
