<?php
declare(strict_types=1);

namespace app\\payment;

use LogicException;

final class PaymentChannelRegistry
{
    /** @var array<string, PaymentChannelAdapter> */
    private array $adapters = [];

    public function register(PaymentChannelAdapter $adapter): void
    {
        $code = strtoupper($adapter->code());
        if (isset($this->adapters[$code])) {
            throw new LogicException("Payment adapter already registered: {$code}");
        }
        $this->adapters[$code] = $adapter;
    }

    public function get(string $code): PaymentChannelAdapter
    {
        $key = strtoupper($code);
        return $this->adapters[$key]
            ?? throw new LogicException("Unsupported payment channel: {$key}");
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->adapters);
    }
}