<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;
use think\facade\Db;

final class PaymentCallbackProcessor
{
    public function __construct(private readonly PaymentChannelRegistry $registry)
    {
    }

    /**
     * Acknowledge a successful payment only after the order and wallet ledger
     * have been committed atomically.
     *
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $configuration
     */
    public function process(
        string $channelCode,
        array $headers,
        array $payload,
        array $configuration,
    ): bool {
        $channelCode = strtoupper($channelCode);
        $adapter = $this->registry->get($channelCode);

        if (!$adapter->verifyCallback($headers, $payload, $configuration)) {
            throw new RuntimeException('Payment callback signature verification failed.');
        }
        if (!self::isSuccessfulNotification($channelCode, $payload)) {
            return false;
        }

        $orderNo = trim((string) ($payload['order_id'] ?? $payload['out_trade_no'] ?? ''));
        $providerAmount = self::scalarString($payload['amount'] ?? $payload['money'] ?? null);
        $providerTradeId = trim((string) (
            $payload['trade_id']
            ?? $payload['trade_no']
            ?? $payload['block_transaction_id']
            ?? ''
        ));

        if ($orderNo === '' || $providerAmount === '' || $providerTradeId === '') {
            throw new RuntimeException('Payment callback is missing order, amount, or provider transaction ID.');
        }

        return Db::transaction(static function () use (
            $channelCode,
            $orderNo,
            $providerAmount,
            $providerTradeId,
            $payload,
        ): bool {
            $rows = Db::query(
                'SELECT * FROM auth_payment_orders WHERE order_no = ? AND channel_code = ? LIMIT 1 FOR UPDATE',
                [$orderNo, $channelCode],
            );
            $order = $rows[0] ?? null;

            if (!is_array($order)) {
                throw new RuntimeException('Payment callback references an unknown local order.');
            }
            if (($order['status'] ?? '') === 'PAID') {
                return true;
            }
            if (($order['status'] ?? '') !== 'PENDING') {
                throw new RuntimeException('Payment order is not eligible for automatic settlement.');
            }

            if (self::normalizeDecimal($providerAmount) !== self::normalizeDecimal((string) $order['provider_amount'])) {
                throw new RuntimeException('Callback amount differs from the immutable provider order snapshot.');
            }

            $callbackCurrency = strtoupper(trim((string) ($payload['currency'] ?? '')));
            if ($callbackCurrency !== '' && $callbackCurrency !== strtoupper((string) $order['provider_currency'])) {
                throw new RuntimeException('Callback currency differs from the immutable provider order snapshot.');
            }

            $currency = PaymentCurrency::from((string) $order['currency']);
            $walletRows = Db::query(
                'SELECT * FROM auth_wallet_accounts WHERE user_id = ? AND currency = ? LIMIT 1 FOR UPDATE',
                [(int) $order['user_id'], $currency->value],
            );
            $wallet = $walletRows[0] ?? null;
            if (!is_array($wallet)) {
                throw new RuntimeException('Wallet account is not initialized for the payment currency.');
            }

            $ledgerKey = 'payment:' . (int) $order['id'];
            $existingLedger = Db::query(
                'SELECT id FROM auth_wallet_transactions WHERE idempotency_key = ? LIMIT 1',
                [$ledgerKey],
            );
            if ($existingLedger !== []) {
                throw new RuntimeException('A payment ledger already exists for a pending order; manual reconciliation is required.');
            }

            $credit = (string) $order['amount'];
            if (self::normalizeDecimal($credit) === '0') {
                throw new RuntimeException('Payment order amount must be greater than zero.');
            }

            $before = (string) $wallet['balance'];
            Db::execute(
                'UPDATE auth_wallet_accounts SET balance = balance + ?, updated_at = NOW() WHERE id = ?',
                [$credit, (int) $wallet['id']],
            );
            $balanceRows = Db::query(
                'SELECT balance FROM auth_wallet_accounts WHERE id = ? LIMIT 1',
                [(int) $wallet['id']],
            );
            $after = (string) ($balanceRows[0]['balance'] ?? '');
            if ($after === '') {
                throw new RuntimeException('Unable to read the wallet balance after settlement.');
            }

            Db::execute(
                'INSERT INTO auth_wallet_transactions
                    (user_id, currency, direction, amount, balance_before, balance_after, business_type, business_id, idempotency_key, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    (int) $order['user_id'],
                    $currency->value,
                    'CREDIT',
                    $credit,
                    $before,
                    $after,
                    'PAYMENT',
                    (int) $order['id'],
                    $ledgerKey,
                    'Online payment top-up',
                ],
            );

            Db::execute(
                'UPDATE auth_payment_orders
                 SET status = ?, provider_trade_id = ?, paid_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND status = ?',
                ['PAID', $providerTradeId, (int) $order['id'], 'PENDING'],
            );

            $eventKey = hash('sha256', implode('|', [
                $channelCode,
                $orderNo,
                $providerTradeId,
                self::normalizeDecimal($providerAmount),
            ]));
            $payloadHash = hash('sha256', json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
            Db::execute(
                'INSERT IGNORE INTO auth_payment_callback_events
                    (channel_code, event_key, order_no, provider_trade_id, payload_sha256, processing_status, received_at, processed_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$channelCode, $eventKey, $orderNo, $providerTradeId, $payloadHash, 'PROCESSED'],
            );

            return true;
        });
    }

    /** @param array<string, mixed> $payload */
    public static function isSuccessfulNotification(string $channelCode, array $payload): bool
    {
        return match (strtoupper($channelCode)) {
            'GMPAY', 'EPUSDT' => (int) ($payload['status'] ?? 0) === 2,
            'EPAY' => strtoupper((string) ($payload['trade_status'] ?? '')) === 'TRADE_SUCCESS',
            default => false,
        };
    }

    public static function normalizeDecimal(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,8})?$/', $value)) {
            throw new RuntimeException('Invalid decimal amount in payment data.');
        }

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($fraction, '0');

        return $integer . ($fraction === '' ? '' : '.' . $fraction);
    }

    private static function scalarString(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
