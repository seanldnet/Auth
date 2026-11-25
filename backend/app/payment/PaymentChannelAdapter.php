<?php
declare(strict_types=1);

namespace app\payment;

interface PaymentChannelAdapter
{
    public function code(): string;

    /** @return list<PaymentCurrency> */
    public function supportedCurrencies(): array;

    /**
     * Provider-specific request fields and signatures must follow official
     * merchant documentation. Secrets must never be returned to the client.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $configuration
     */
    public function createCashier(array $order, array $configuration): CashierPayload;

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $configuration
     */
    public function verifyCallback(array $headers, array $payload, array $configuration): bool;

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $configuration
     * @return array{status: string, provider_transaction_id: ?string, amount: string, currency: string}
     */
    public function queryOrder(array $order, array $configuration): array;
}