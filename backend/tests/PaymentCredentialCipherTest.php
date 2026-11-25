<?php
declare(strict_types=1);

namespace tests;

use app\payment\PaymentCredentialCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentCredentialCipherTest extends TestCase
{
    public function testCredentialsAreAuthenticatedAndEncrypted(): void
    {
        $previous = getenv('APP_KEY');
        putenv('APP_KEY=unit-test-key-that-is-longer-than-thirty-two-characters');

        try {
            $secret = json_encode(['secret_key' => 'merchant-secret'], JSON_THROW_ON_ERROR);
            $encrypted = PaymentCredentialCipher::encrypt($secret);

            self::assertNotSame($secret, $encrypted);
            self::assertSame($secret, PaymentCredentialCipher::decrypt($encrypted));
        } finally {
            if ($previous === false) {
                putenv('APP_KEY');
            } else {
                putenv('APP_KEY=' . $previous);
            }
        }
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $previous = getenv('APP_KEY');
        putenv('APP_KEY=unit-test-key-that-is-longer-than-thirty-two-characters');

        try {
            $encrypted = PaymentCredentialCipher::encrypt('secret');
            $bytes = base64_decode($encrypted, true);
            self::assertIsString($bytes);
            $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 1);

            $this->expectException(RuntimeException::class);
            PaymentCredentialCipher::decrypt(base64_encode($bytes));
        } finally {
            if ($previous === false) {
                putenv('APP_KEY');
            } else {
                putenv('APP_KEY=' . $previous);
            }
        }
    }
}
