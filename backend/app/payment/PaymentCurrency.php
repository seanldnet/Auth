<?php
declare(strict_types=1);

namespace app\payment;

enum PaymentCurrency: string
{
    case CNY = 'CNY';
    case USDT = 'USDT';

    public function displaySymbol(): string
    {
        return match ($this) {
            self::CNY => '¥',
            self::USDT => 'U',
        };
    }

    public function displayAmount(string $amount): string
    {
        return match ($this) {
            self::CNY => $this->displaySymbol() . $amount,
            self::USDT => $amount . ' ' . $this->displaySymbol(),
        };
    }
}