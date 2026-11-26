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
        if ($this === self::CNY) {
            [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
            return $this->displaySymbol() . $integer . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
        }

        return $amount . ' ' . $this->displaySymbol();
    }
}