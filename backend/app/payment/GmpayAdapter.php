<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;

final class GmpayAdapter implements PaymentChannelAdapter
{
    public function code(): string
    {
        return 'GMPAY';
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
            throw new RuntimeException('GMPay merchant credentials and explicit provider amount/currency mapping are required.');
        }

        $hasToken = !empty($order['token']);
        $hasNetwork = !empty($order['network']);
        if ($hasToken !== $hasNetwork) {
            throw new RuntimeException('Provider token and network must be supplied together.');
        }

        $notifyUrl = (string) ($configuration['notify_url'] ?? '');
        if ((parse_url($notifyUrl, PHP_URL_SCHEME) ?: '') !== 'https') {
            throw new RuntimeException('Payment notification URL must use HTTPS.');
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
            throw new RuntimeException('GMPay rejected the payment order.');
        }

        $data = $response['data'];
        $paymentUrl = (string) ($data['payment_url'] ?? '');
        $parts = parse_url($paymentUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = array_map('strtolower', (array) ($configuration['checkout_hosts'] ?? []));

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || !in_array($host, $allowed, true)) {
            throw new RuntimeException('GMPay cashier URL failed HTTPS/host allow-list validation.');
        }

        return new CashierPayload(
            (string) $order['platform_order_no'],
            $this->code(),
            PaymentCurrency::from((string) $order['currency']),
            (string) $order['amount'],
            gmdate(DATE_ATOM, (int) ($data['expiration_time'] ?? time() + 900)),
            $paymentUrl,
            providerTradeId: isset($data['trade_id']) ? (string) $data['trade_id'] : null,
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
        $pid = (string) ($configuration['pid'] ?? '');
        $secret = (string) ($configuration['secret_key'] ?? '');
        $tradeId = (string) ($order['provider_trade_id'] ?? '');
        if ($pid === '' || $secret === '' || $tradeId === '') {
            throw new RuntimeException('GMPay credentials and provider trade ID are required.');
        }

        $parameters = ['pid' => $pid, 'trade_id' => $tradeId];
        $parameters['signature'] = GmpaySigner::sign($parameters, $secret);
        $response = GmpayHttpClient::get(
            (string) $configuration['base_url'],
            '/payments/gmpay/v1/order/query',
            $parameters,
        );
        $data = (array) ($response['data'] ?? []);
        $status = (int) ($data['status'] ?? 0);

        return [
            'status' => match ($status) {
                2 => 'SUCCEEDED',
                3 => 'EXPIRED',
                4 => 'AWAITING_SELECTION',
                1 => 'PENDING',
                default => 'UNKNOWN',
            },
            'provider_transaction_id' => isset($data['block_transaction_id'])
                ? (string) $data['block_transaction_id']
                : null,
            'amount' => (string) ($data['amount'] ?? ''),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'trade_id' => isset($data['trade_id']) ? (string) $data['trade_id'] : $tradeId,
            'order_id' => isset($data['order_id']) ? (string) $data['order_id'] : (string) $order['platform_order_no'],
        ];
    }
}