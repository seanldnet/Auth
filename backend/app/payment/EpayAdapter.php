<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;

final class EpayAdapter implements PaymentChannelAdapter
{
    public function code(): string
    {
        return 'EPAY';
    }

    public function supportedCurrencies(): array
    {
        return [PaymentCurrency::CNY, PaymentCurrency::USDT];
    }

    public function createCashier(array $order, array $configuration): CashierPayload
    {
        $pid = (string) ($configuration['pid'] ?? '');
        $secret = (string) ($configuration['secret_key'] ?? '');
        $providerAmount = (string) ($order['provider_amount'] ?? '');
        $providerCurrency = strtolower((string) ($order['provider_currency'] ?? ''));

        if ($pid === '' || $secret === '' || $providerAmount === '' || $providerCurrency === '') {
            throw new RuntimeException('EPay credentials and explicit provider amount/currency are required.');
        }

        $notifyUrl = (string) ($configuration['notify_url'] ?? '');
        if ((parse_url($notifyUrl, PHP_URL_SCHEME) ?: '') !== 'https') {
            throw new RuntimeException('Payment notification URL must use HTTPS.');
        }
        if (!empty($order['token']) !== !empty($order['network'])) {
            throw new RuntimeException('Provider token and network must be supplied together.');
        }

        $parameters = [
            'pid' => $pid,
            'money' => $providerAmount,
            'out_trade_no' => (string) $order['platform_order_no'],
            'notify_url' => (string) $configuration['notify_url'],
            'return_url' => (string) ($configuration['return_url'] ?? ''),
            'name' => (string) ($order['subject'] ?? 'Auth balance top-up'),
            'currency' => $providerCurrency,
            'sign_type' => 'MD5',
        ];

        if (!empty($order['payment_type'])) {
            $parameters['type'] = (string) $order['payment_type'];
        }
        if (!empty($order['token']) && !empty($order['network'])) {
            $parameters['token'] = strtolower((string) $order['token']);
            $parameters['network'] = strtolower((string) $order['network']);
        }

        $parameters['sign'] = EpaySigner::sign($parameters, $secret);
        $responseMode = (string) ($configuration['response_mode'] ?? 'json');
        $createPath = (string) ($configuration['create_path'] ?? '/payments/epay/v1/order/create-transaction/mapi.php');

        if ($responseMode === 'redirect') {
            $paymentUrl = GmpayHttpClient::postFormRedirect(
                (string) $configuration['base_url'],
                $createPath,
                $parameters,
            );
            $locationParts = parse_url($paymentUrl);
            if (!isset($locationParts['host']) && str_starts_with($paymentUrl, '/')) {
                $baseParts = parse_url((string) $configuration['base_url']);
                $paymentUrl = ($baseParts['scheme'] ?? 'https') . '://' . $baseParts['host'] . $paymentUrl;
            }
            $providerTradeId = basename((string) parse_url($paymentUrl, PHP_URL_PATH));
            $expiresAt = gmdate(DATE_ATOM, time() + (int) ($configuration['order_ttl_seconds'] ?? 900));
        } else {
            $response = GmpayHttpClient::postForm((string) $configuration['base_url'], $createPath, $parameters);
            if (($response['code'] ?? null) !== 1 || empty($response['payurl'])) {
                throw new RuntimeException('EPay rejected the payment order.');
            }
            $paymentUrl = (string) $response['payurl'];
            $providerTradeId = isset($response['trade_no']) ? (string) $response['trade_no'] : null;
            $expiresAt = gmdate(DATE_ATOM, time() + (int) ($configuration['order_ttl_seconds'] ?? 900));
        }

        $parts = parse_url($paymentUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = array_map('strtolower', (array) ($configuration['checkout_hosts'] ?? []));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || !in_array($host, $allowed, true)) {
            throw new RuntimeException('EPay cashier URL failed HTTPS/host allow-list validation.');
        }

        return new CashierPayload(
            (string) $order['platform_order_no'],
            $this->code(),
            PaymentCurrency::from((string) $order['currency']),
            (string) $order['amount'],
            $expiresAt,
            $paymentUrl,
            providerTradeId: $providerTradeId,
        );
    }

    public function verifyCallback(array $headers, array $payload, array $configuration): bool
    {
        $pid = (string) ($payload['pid'] ?? '');
        return $pid !== ''
            && hash_equals((string) ($configuration['pid'] ?? ''), $pid)
            && EpaySigner::verify($payload, (string) ($configuration['secret_key'] ?? ''));
    }

    public function queryOrder(array $order, array $configuration): array
    {
        $pid = (string) ($configuration['pid'] ?? '');
        $secret = (string) ($configuration['secret_key'] ?? '');
        $tradeNo = (string) ($order['provider_trade_id'] ?? '');
        if ($pid === '' || $secret === '' || $tradeNo === '') {
            throw new RuntimeException('EPay credentials and provider trade number are required.');
        }

        if (($configuration['query_mode'] ?? 'epay_api') === 'epusdt_status') {
            $response = GmpayHttpClient::get(
                (string) $configuration['base_url'],
                '/pay/check-status/' . rawurlencode($tradeNo),
            );
            $providerStatus = (int) ($response['data']['status'] ?? 0);
            $status = match ($providerStatus) {
                2 => 'SUCCEEDED',
                3 => 'EXPIRED',
                5 => 'PROCESSING',
                6 => 'REVIEW',
                1, 4 => 'PENDING',
                default => 'UNKNOWN',
            };
        } else {
            $parameters = ['act' => 'order', 'pid' => $pid, 'trade_no' => $tradeNo, 'sign_type' => 'MD5'];
            $parameters['sign'] = EpaySigner::sign($parameters, $secret);
            $response = GmpayHttpClient::get(
                (string) $configuration['base_url'],
                (string) ($configuration['query_path'] ?? '/payments/epay/v1/order/create-transaction/api.php'),
                $parameters,
            );
            $status = (int) ($response['status'] ?? 0) === 1 ? 'SUCCEEDED' : 'PENDING';
        }

        return [
            'status' => $status,
            'provider_transaction_id' => isset($response['trade_no']) ? (string) $response['trade_no'] : null,
            'amount' => (string) $order['provider_amount'],
            'currency' => (string) $order['provider_currency'],
        ];
    }
}