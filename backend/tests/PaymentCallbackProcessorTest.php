<?php
declare(strict_types=1);

namespace tests;

use app\payment\PaymentCallbackProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentCallbackProcessorTest extends TestCase
{
    public function testDecimalNormalizationDoesNotUseFloatingPoint(): void
    {
        self::assertSame('0', PaymentCallbackProcessor::normalizeDecimal('0.00000000'));
        self::assertSame('100', PaymentCallbackProcessor::normalizeDecimal('100.0000'));
        self::assertSame('12.34', PaymentCallbackProcessor::normalizeDecimal('12.34000000'));
        self::assertSame('0.00000001', PaymentCallbackProcessor::normalizeDecimal('0.00000001'));
    }

    public function testInvalidDecimalIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        PaymentCallbackProcessor::normalizeDecimal('1e3');
    }

    public function testOnlySettledProviderStatusesCanCreditWallets(): void
    {
        self::assertTrue(PaymentCallbackProcessor::isSuccessfulNotification('GMPAY', ['status' => 2]));
        self::assertTrue(PaymentCallbackProcessor::isSuccessfulNotification('EPUSDT', ['status' => 2]));
        self::assertFalse(PaymentCallbackProcessor::isSuccessfulNotification('GMPAY', ['status' => 5]));
        self::assertFalse(PaymentCallbackProcessor::isSuccessfulNotification('EPUSDT', ['status' => 6]));
        self::assertTrue(PaymentCallbackProcessor::isSuccessfulNotification('EPAY', ['trade_status' => 'TRADE_SUCCESS']));
        self::assertFalse(PaymentCallbackProcessor::isSuccessfulNotification('EPAY', ['trade_status' => 'TRADE_FINISHED']));
        self::assertFalse(PaymentCallbackProcessor::isSuccessfulNotification('UNKNOWN', ['status' => 2]));
    }
}
