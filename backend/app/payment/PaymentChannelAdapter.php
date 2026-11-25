<?php
declare(strict_types=1);

namespace app\\payment;

interface PaymentChannelAdapter
{
    public function code(): string;

    /** @return list<PaymentCurrency> */
    public function supportedCurrencies(): array;

    /**
     * Create a provider payment and return its cashier hand-off information.
     *
     * Provider-specific request fields and signatures must follow the official
     * merchant integration documentation. Secrets must never be returned here.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $configuration
     */
    public function createCashier(array $order, array $configuration): CashierPayload;

    /**
     * Verify the provider callback signature and merchant identity.
     *
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $configuration
     */
    public function verifyCallback(array $headers, array $payload, array $configuration): bool;

    /**
     * Query provider-side order status using authenticated server-to-server API.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $configuration
     * @return array{status: string, provider_transaction_id: ?string, amount: string, currency: string}
     */
    public function queryOrder(array $order, array $configuration): array;
}