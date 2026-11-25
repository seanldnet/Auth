<?php
declare(strict_types=1);

namespace tests;

use app\payment\PaymentCurrency;
use PHPUnit\Framework\TestCase;

final class PaymentCurrencyTest extends TestCase
{
    public function testCurrenciesAreDisplayedIndependentlyWithoutConversion(): void
    {
        self::assertSame('¥100.00', PaymentCurrency::CNY->displayAmount('100.00'));
        self::assertSame('100.00 U', PaymentCurrency::USDT->displayAmount('100.00'));
        self::assertNotSame(
            PaymentCurrency::CNY->displayAmount('100.00'),
            PaymentCurrency::USDT->displayAmount('100.00'),
        );
    }
}