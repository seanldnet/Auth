<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;
use Throwable;
use think\facade\Db;

final class PaymentOrderService
{
    public function __construct(private readonly PaymentChannelRegistry $registry)
    {
    }

    /**
     * Create a platform order and then request the provider cashier URL.
     * The provider amount is deliberately identical to the platform amount:
     * this service never performs an exchange-rate conversion.
     *
     * @param array<string, scalar|null> $options
     * @return array<string, mixed>
     */
    public function create(
        int $userId,
        string $channelCode,
        PaymentCurrency $currency,
        string $amount,
        string $idempotencyKey,
        array $options = [],
    ): array {
        if ($userId < 1) {
            throw new RuntimeException('A valid authenticated user is required.');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $idempotencyKey)) {
            throw new RuntimeException('A valid idempotency key is required.');
        }

        $amount = self::normalizePositiveAmount($amount, $currency);
        $channelCode = strtoupper($channelCode);
        $paymentType = trim((string) ($options['payment_type'] ?? ''));
        $token = trim((string) ($options['token'] ?? ''));
        $network = trim((string) ($options['network'] ?? ''));
        if (($token === '') !== ($network === '')) {
            throw new RuntimeException('Provider token and network must be selected together.');
        }

        $existingRows = Db::query(
            'SELECT * FROM auth_payment_orders WHERE user_id = ? AND idempotency_key = ? LIMIT 1',
            [$userId, $idempotencyKey],
        );
        if (isset($existingRows[0]) && is_array($existingRows[0])) {
            $existing = $existingRows[0];
            if (strtoupper((string) $existing['channel_code']) !== $channelCode
                || (string) $existing['currency'] !== $currency->value
                || PaymentCallbackProcessor::normalizeDecimal((string) $existing['amount']) !== PaymentCallbackProcessor::normalizeDecimal($amount)
                || (string) ($existing['payment_type'] ?? '') !== $paymentType
                || (string) ($existing['provider_token'] ?? '') !== $token
                || (string) ($existing['provider_network'] ?? '') !== $network
            ) {
                throw new RuntimeException('Idempotency key was already used with different payment parameters.');
            }

            $existingToken = self::checkoutToken($userId, (string) $existing['order_no'], $idempotencyKey);
            return self::presentOrder($existing, $existingToken);
        }

        $adapter = $this->registry->get($channelCode);

        if (!in_array($currency, $adapter->supportedCurrencies(), true)) {
            throw new RuntimeException('The payment channel does not support this platform currency.');
        }

        $channelRow = Db::name('payment_channels')
            ->where('channel_code', $channelCode)
            ->where('enabled', 1)
            ->find();

        if (!is_array($channelRow)) {
            throw new RuntimeException('The selected payment channel is disabled or unavailable.');
        }

        $allowedCurrencies = json_decode((string) $channelRow['allowed_currencies'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($allowedCurrencies) || !in_array($currency->value, $allowedCurrencies, true)) {
            throw new RuntimeException('The payment channel is not enabled for this platform currency.');
        }

        $configuration = json_decode((string) $channelRow['configuration'], true, 512, JSON_THROW_ON_ERROR);
        $credentialsJson = PaymentCredentialCipher::decrypt((string) $channelRow['credentials_ciphertext']);
        $credentials = json_decode($credentialsJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($configuration) || !is_array($credentials)) {
            throw new RuntimeException('Payment channel configuration is invalid.');
        }

        $configuration = array_merge($configuration, $credentials, [
            'pid' => (string) $channelRow['merchant_pid'],
        ]);

        $providerCurrencyMap = (array) ($configuration['provider_currency_by_platform_currency'] ?? []);
        $providerCurrency = strtolower((string) ($providerCurrencyMap[$currency->value] ?? ''));
        if ($providerCurrency === '') {
            throw new RuntimeException('The provider currency mapping must be explicitly configured for this platform currency.');
        }
        if ($channelCode === 'EPAY'
            && empty($configuration['send_currency_override'])
            && strtolower((string) ($configuration['epay_default_currency'] ?? '')) !== $providerCurrency
        ) {
            throw new RuntimeException('EPay provider currency must match the gateway default or explicitly enable the currency override.');
        }

        $providerAmount = $amount;
        if (PaymentCallbackProcessor::normalizeDecimal($providerAmount) !== PaymentCallbackProcessor::normalizeDecimal($amount)) {
            throw new RuntimeException('Currency conversion is disabled; provider and platform amounts must be identical.');
        }

        $wallet = Db::name('wallet_accounts')
            ->where('user_id', $userId)
            ->where('currency', $currency->value)
            ->find();
        if (!is_array($wallet)) {
            throw new RuntimeException('The user wallet is not initialized for the selected currency.');
        }

        $orderNo = 'AU' . gmdate('YmdHis') . strtoupper(bin2hex(random_bytes(5)));
        $checkoutToken = self::checkoutToken($userId, $orderNo, $idempotencyKey);
        $checkoutTokenHash = hash('sha256', $checkoutToken);

        try {
            $order = Db::transaction(static function () use (
                $userId,
                $channelCode,
                $currency,
                $amount,
                $providerCurrency,
                $providerAmount,
                $paymentType,
                $token,
                $network,
                $idempotencyKey,
                $orderNo,
                $checkoutTokenHash,
            ): array {
                $existingRows = Db::query(
                    'SELECT * FROM auth_payment_orders WHERE user_id = ? AND idempotency_key = ? LIMIT 1 FOR UPDATE',
                    [$userId, $idempotencyKey],
                );
                if (isset($existingRows[0]) && is_array($existingRows[0])) {
                    return $existingRows[0];
                }

                Db::execute(
                    'INSERT INTO auth_payment_orders
                        (order_no, user_id, idempotency_key, checkout_token_hash, channel_code, currency, amount, provider_currency, provider_amount, payment_type, provider_token, provider_network, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $orderNo,
                        $userId,
                        $idempotencyKey,
                        $checkoutTokenHash,
                        $channelCode,
                        $currency->value,
                        $amount,
                        $providerCurrency,
                        $providerAmount,
                        $paymentType !== '' ? $paymentType : null,
                        $token !== '' ? $token : null,
                        $network !== '' ? $network : null,
                        'CREATING',
                    ],
                );

                $rows = Db::query(
                    'SELECT * FROM auth_payment_orders WHERE order_no = ? LIMIT 1 FOR UPDATE',
                    [$orderNo],
                );
                return $rows[0] ?? throw new RuntimeException('Unable to create the local payment order.');
            });
        } catch (Throwable $exception) {
            $existingRows = Db::query(
                'SELECT * FROM auth_payment_orders WHERE user_id = ? AND idempotency_key = ? LIMIT 1',
                [$userId, $idempotencyKey],
            );
            if (!isset($existingRows[0]) || !is_array($existingRows[0])) {
                throw $exception;
            }
            $order = $existingRows[0];
        }

        if ((string) $order['order_no'] !== $orderNo) {
            if (strtoupper((string) $order['channel_code']) !== $channelCode
                || (string) $order['currency'] !== $currency->value
                || PaymentCallbackProcessor::normalizeDecimal((string) $order['amount']) !== PaymentCallbackProcessor::normalizeDecimal($amount)
                || (string) ($order['payment_type'] ?? '') !== $paymentType
                || (string) ($order['provider_token'] ?? '') !== $token
                || (string) ($order['provider_network'] ?? '') !== $network
            ) {
                throw new RuntimeException('Idempotency key was already used with different payment parameters.');
            }

            $checkoutToken = self::checkoutToken($userId, (string) $order['order_no'], $idempotencyKey);
            return self::presentOrder($order, $checkoutToken);
        }

        if ((string) $order['status'] !== 'CREATING') {
            return self::presentOrder($order, $checkoutToken);
        }

        $providerOrder = [
            'platform_order_no' => $orderNo,
            'currency' => $currency->value,
            'amount' => $amount,
            'provider_currency' => $providerCurrency,
            'provider_amount' => $providerAmount,
            'subject' => (string) ($options['subject'] ?? 'Account top-up'),
            'payment_type' => $paymentType !== '' ? $paymentType : null,
            'token' => $token !== '' ? $token : null,
            'network' => $network !== '' ? $network : null,
        ];

        try {
            $cashier = $adapter->createCashier($providerOrder, $configuration);
            if ($cashier->channelCode !== $channelCode
                || $cashier->currency !== $currency
                || PaymentCallbackProcessor::normalizeDecimal($cashier->amount) !== PaymentCallbackProcessor::normalizeDecimal($amount)
                || $cashier->redirectUrl === null
                || $cashier->providerTradeId === null
                || trim($cashier->providerTradeId) === ''
            ) {
                throw new RuntimeException('Payment adapter returned a cashier payload inconsistent with the local order.');
            }

            $expiresAt = gmdate('Y-m-d H:i:s', strtotime($cashier->expiresAt) ?: time() + 900);
            Db::execute(
                'UPDATE auth_payment_orders
                 SET provider_trade_id = ?, cashier_url = ?, expires_at = ?, status = ?, updated_at = NOW()
                 WHERE id = ? AND status = ?',
                [
                    $cashier->providerTradeId,
                    $cashier->redirectUrl,
                    $expiresAt,
                    'PENDING',
                    (int) $order['id'],
                    'CREATING',
                ],
            );

            $order['provider_trade_id'] = $cashier->providerTradeId;
            $order['cashier_url'] = $cashier->redirectUrl;
            $order['expires_at'] = $expiresAt;
            $order['status'] = 'PENDING';

            return self::presentOrder($order, $checkoutToken);
        } catch (Throwable $exception) {
            Db::execute(
                'UPDATE auth_payment_orders SET status = ?, updated_at = NOW() WHERE id = ? AND status = ?',
                ['REVIEW', (int) $order['id'], 'CREATING'],
            );
            throw new RuntimeException(
                'Payment provider creation could not be confirmed; the order was placed into manual review.',
                previous: $exception,
            );
        }
    }

    /**
     * Public cashier data is protected by a 256-bit opaque token.
     *
     * @return array<string, mixed>
     */
    public function getCashier(string $orderNo, string $token): array
    {
        $rows = Db::query(
            'SELECT order_no, checkout_token_hash, channel_code, currency, amount, cashier_url, status, expires_at, paid_at
             FROM auth_payment_orders WHERE order_no = ? LIMIT 1',
            [$orderNo],
        );
        $order = $rows[0] ?? null;
        if (!is_array($order) || !hash_equals((string) $order['checkout_token_hash'], hash('sha256', $token))) {
            throw new CashierNotFoundException('Cashier order was not found.');
        }

        if (($order['status'] ?? '') === 'PENDING'
            && !empty($order['expires_at'])
            && strtotime((string) $order['expires_at'] . ' UTC') <= time()
        ) {
            Db::execute(
                'UPDATE auth_payment_orders SET status = ?, updated_at = NOW() WHERE order_no = ? AND status = ?',
                ['EXPIRED', $orderNo, 'PENDING'],
            );
            $order['status'] = 'EXPIRED';
        }

        return self::presentOrder($order, $token);
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private static function presentOrder(array $order, string $checkoutToken): array
    {
        $currency = PaymentCurrency::from((string) $order['currency']);

        return [
            'order_no' => (string) $order['order_no'],
            'channel_code' => (string) $order['channel_code'],
            'currency' => $currency->value,
            'amount' => (string) $order['amount'],
            'display_amount' => $currency->displayAmount((string) $order['amount']),
            'status' => (string) $order['status'],
            'provider_cashier_url' => $order['cashier_url'] ?? null,
            'expires_at' => $order['expires_at'] ?? null,
            'paid_at' => $order['paid_at'] ?? null,
            'checkout_url' => '/checkout/' . rawurlencode((string) $order['order_no']) . '/' . rawurlencode($checkoutToken),
            'checkout_token' => $checkoutToken,
        ];
    }

    private static function normalizePositiveAmount(string $amount, PaymentCurrency $currency): string
    {
        $normalized = PaymentCallbackProcessor::normalizeDecimal($amount);
        if ($normalized === '0') {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        if ($currency === PaymentCurrency::CNY) {
            [, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
            if (strlen($fraction) > 2) {
                throw new RuntimeException('CNY amounts support at most two decimal places.');
            }
        }

        return $normalized;
    }

    private static function checkoutToken(int $userId, string $orderNo, string $idempotencyKey): string
    {
        $masterKey = (string) getenv('APP_KEY');
        if (strlen($masterKey) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters.');
        }

        return hash_hmac(
            'sha256',
            implode('|', ['cashier', $userId, $orderNo, $idempotencyKey]),
            hash('sha256', $masterKey, true),
        );
    }
}
