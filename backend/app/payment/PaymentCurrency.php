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
            $fraction = rtrim($fraction, '0');
            if (strlen($fraction) > 2) {
                throw new \InvalidArgumentException('CNY display amount exceeds two decimal places.');
            }

            return $this->displaySymbol() . $integer . '.' . str_pad($fraction, 2, '0');
        }

        return $amount . ' ' . $this->displaySymbol();
    }
}