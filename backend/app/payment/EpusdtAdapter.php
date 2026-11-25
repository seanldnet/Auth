<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;

final class EpusdtAdapter implements PaymentChannelAdapter
{
    public function code(): string
    {
        return 'EPUSDT';
    }

    public function supportedCurrencies(): array
    {
        return [PaymentCurrency::CNY, PaymentCurrency::USDT];
    }

    public function createCashier(array $order, array $configuration): CashierPayload
    {
        $pid = (string) ($configuration['pid'] ?? '');
        $secret = (string) ($configuration['secret_key'] ?? '');
        $providerCurrency = strtolower((string) ($order['provider_currency'] ?? ''));
        $amount = (string) ($order['provider_amount'] ?? '');

        if ($pid === '' || $secret === '' || !in_array($providerCurrency, ['cny', 'usd'], true) || $amount === '') {
            throw new RuntimeException('EPUSDT merchant credentials and explicit provider amount/currency mapping are required.');
        }

        $parameters = [
            'pid' => $pid,
            'order_id' => (string) $order['platform_order_no'],
            'currency' => $providerCurrency,
            'amount' => $amount,
            'notify_url' => (string) $configuration['notify_url'],
            'redirect_url' => (string) ($configuration['return_url'] ?? ''),
            'name' => (string) ($order['subject'] ?? 'Auth balance top-up'),
        ];

        if (!empty($order['token']) && !empty($order['network'])) {
            $parameters['token'] = strtolower((string) $order['token']);
            $parameters['network'] = strtolower((string) $order['network']);
        }

        $parameters['signature'] = GmpaySigner::sign($parameters, $secret);
        $response = GmpayHttpClient::postJson(
            (string) $configuration['base_url'],
            '/payments/gmpay/v1/order/create-transaction',
            $parameters,
        );

        if (($response['status_code'] ?? null) !== 200 || !is_array($response['data'] ?? null)) {
            throw new RuntimeException('EPUSDT rejected the payment order.');
        }

        $data = $response['data'];
        $paymentUrl = (string) ($data['payment_url'] ?? '');
        self::assertCheckoutUrl($paymentUrl, (array) ($configuration['checkout_hosts'] ?? []));

        return new CashierPayload(
            (string) $order['platform_order_no'],
            $this->code(),
            PaymentCurrency::from((string) $order['currency']),
            (string) $order['amount'],
            gmdate(DATE_ATOM, (int) ($data['expiration_time'] ?? time() + 900)),
            $paymentUrl,
        );
    }

    public function verifyCallback(array $headers, array $payload, array $configuration): bool
    {
        $pid = (string) ($payload['pid'] ?? '');
        return $pid !== ''
            && hash_equals((string) ($configuration['pid'] ?? ''), $pid)
            && GmpaySigner::verify($payload, (string) ($configuration['secret_key'] ?? ''));
    }

    public function queryOrder(array $order, array $configuration): array
    {
        $tradeId = (string) ($order['provider_trade_id'] ?? '');
        if ($tradeId === '') {
            throw new RuntimeException('EPUSDT provider trade ID is required for status lookup.');
        }

        $response = GmpayHttpClient::get(
            (string) $configuration['base_url'],
            '/pay/check-status/' . rawurlencode($tradeId),
        );
        $status = (int) ($response['data']['status'] ?? 0);

        return [
            'status' => match ($status) {
                2 => 'SUCCEEDED',
                3 => 'EXPIRED',
                5 => 'PROCESSING',
                6 => 'REVIEW',
                1, 4 => 'PENDING',
                default => 'UNKNOWN',
            },
            'provider_transaction_id' => isset($order['provider_transaction_id'])
                ? (string) $order['provider_transaction_id']
                : null,
            'amount' => (string) $order['provider_amount'],
            'currency' => (string) $order['provider_currency'],
        ];
    }

    /** @param list<string> $allowedHosts */
    private static function assertCheckoutUrl(string $url, array $allowedHosts): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = array_map('strtolower', $allowedHosts);

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || !in_array($host, $allowed, true)) {
            throw new RuntimeException('EPUSDT cashier URL failed HTTPS/host allow-list validation.');
        }
    }
}