<?php
declare(strict_types=1);

namespace app\\payment;

final readonly class CashierPayload
{
    /**
     * @param array<string, scalar|null> $formFields
     */
    public function __construct(
        public string $platformOrderNo,
        public string $channelCode,
        public PaymentCurrency $currency,
        public string $amount,
        public string $expiresAt,
        public ?string $redirectUrl = null,
        public array $formFields = [],
    ) {
        if ($redirectUrl === null && $formFields === []) {
            throw new \\InvalidArgumentException('A cashier redirect URL or form payload is required.');
        }
    }
}