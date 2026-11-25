<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;
use think\facade\Db;

final class PaymentChannelAdminService
{
    private const CHANNELS = [
        'ALIPAY' => ['name' => '支付宝官方支付', 'adapter' => false, 'currencies' => ['CNY']],
        'WECHAT_PAY' => ['name' => '微信支付官方接口', 'adapter' => false, 'currencies' => ['CNY']],
        'EPAY' => ['name' => '易支付', 'adapter' => true, 'currencies' => ['CNY', 'USDT']],
        'EPUSDT' => ['name' => 'EPUSDT', 'adapter' => true, 'currencies' => ['CNY', 'USDT']],
        'GMPAY' => ['name' => 'GMPAY', 'adapter' => true, 'currencies' => ['CNY', 'USDT']],
    ];

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $rows = Db::name('payment_channels')->select()->toArray();
        $configured = [];
        foreach ($rows as $row) {
            $configured[(string) $row['channel_code']] = $row;
        }

        $result = [];
        foreach (self::CHANNELS as $code => $definition) {
            $row = $configured[$code] ?? null;
            $configuration = is_array($row)
                ? json_decode((string) $row['configuration'], true)
                : [];
            $result[] = [
                'channel_code' => $code,
                'display_name' => (string) ($row['display_name'] ?? $definition['name']),
                'adapter_available' => $definition['adapter'],
                'enabled' => (bool) ($row['enabled'] ?? false),
                'allowed_currencies' => is_array($row)
                    ? (json_decode((string) $row['allowed_currencies'], true) ?: [])
                    : $definition['currencies'],
                'merchant_pid_masked' => self::mask((string) ($row['merchant_pid'] ?? '')),
                'has_credentials' => is_array($row) && (string) ($row['credentials_ciphertext'] ?? '') !== '',
                'configuration' => is_array($configuration) ? $configuration : [],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(string $channelCode, array $input, int $actorUserId, string $ipAddress, string $userAgent): array
    {
        $channelCode = strtoupper($channelCode);
        $definition = self::CHANNELS[$channelCode] ?? null;
        if ($definition === null) {
            throw new RuntimeException('Unknown payment channel.');
        }

        $displayName = trim((string) ($input['display_name'] ?? $definition['name']));
        $merchantPid = trim((string) ($input['merchant_pid'] ?? ''));
        $enabled = (bool) ($input['enabled'] ?? false);
        $allowedCurrencies = array_values(array_unique(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            (array) ($input['allowed_currencies'] ?? []),
        )));
        $allowedCurrencies = array_values(array_filter($allowedCurrencies, static fn (string $value): bool => $value !== ''));

        if ($displayName === '' || $merchantPid === '' || $allowedCurrencies === []) {
            throw new RuntimeException('Channel name, merchant PID and at least one currency are required.');
        }
        foreach ($allowedCurrencies as $currency) {
            if (!in_array($currency, ['CNY', 'USDT'], true)
                || !in_array($currency, $definition['currencies'], true)
            ) {
                throw new RuntimeException('The selected currency is not supported by this channel type.');
            }
        }
        if (in_array($channelCode, ['ALIPAY', 'WECHAT_PAY'], true) && $allowedCurrencies !== ['CNY']) {
            throw new RuntimeException('Official Alipay and WeChat Pay channels are CNY-only.');
        }
        if ($enabled && !$definition['adapter']) {
            throw new RuntimeException('This channel adapter is not implemented yet and cannot be enabled.');
        }

        $configuration = (array) ($input['configuration'] ?? []);
        $baseUrl = self::httpsUrl((string) ($configuration['base_url'] ?? ''), 'Gateway base URL');
        $notifyUrl = self::httpsUrl((string) ($configuration['notify_url'] ?? ''), 'Notification URL');
        $returnUrl = trim((string) ($configuration['return_url'] ?? ''));
        if ($returnUrl !== '') {
            self::httpsUrl($returnUrl, 'Return URL');
        }

        $checkoutHosts = array_values(array_unique(array_map(
            static fn ($host): string => strtolower(trim((string) $host)),
            (array) ($configuration['checkout_hosts'] ?? []),
        )));
        $checkoutHosts = array_values(array_filter($checkoutHosts, static fn (string $host): bool => $host !== ''));
        if ($checkoutHosts === []) {
            throw new RuntimeException('At least one cashier host must be allow-listed.');
        }
        foreach ($checkoutHosts as $host) {
            if (!preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host)
                || str_contains($host, '..')
            ) {
                throw new RuntimeException('Cashier host allow-list contains an invalid hostname.');
            }
        }

        $providerCurrencyMap = (array) ($configuration['provider_currency_by_platform_currency'] ?? []);
        if ($definition['adapter']) {
            foreach ($allowedCurrencies as $currency) {
                $providerCurrency = strtolower((string) ($providerCurrencyMap[$currency] ?? ''));
                if (!in_array($providerCurrency, ['cny', 'usd'], true)) {
                    throw new RuntimeException('Set an explicit CNY/USD provider currency mapping for every enabled platform currency.');
                }
            }
        }

        $sendCurrencyOverride = (bool) ($configuration['send_currency_override'] ?? false);
        $epayDefaultCurrency = strtolower((string) ($configuration['epay_default_currency'] ?? ''));
        if ($channelCode === 'EPAY' && !$sendCurrencyOverride) {
            if (!in_array($epayDefaultCurrency, ['cny', 'usd'], true)) {
                throw new RuntimeException('Set the EPay gateway default currency or enable the explicit currency override.');
            }
            foreach ($allowedCurrencies as $currency) {
                if (strtolower((string) ($providerCurrencyMap[$currency] ?? '')) !== $epayDefaultCurrency) {
                    throw new RuntimeException('Without currency override, all enabled platform currencies must match the EPay gateway default currency.');
                }
            }
        }

        $orderTtl = (int) ($configuration['order_ttl_seconds'] ?? 900);
        if ($orderTtl < 60 || $orderTtl > 3600) {
            throw new RuntimeException('Payment order TTL must be between 60 and 3600 seconds.');
        }

        $normalizedConfiguration = [
            'base_url' => $baseUrl,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'checkout_hosts' => $checkoutHosts,
            'provider_currency_by_platform_currency' => $providerCurrencyMap,
            'send_currency_override' => $sendCurrencyOverride,
            'epay_default_currency' => $epayDefaultCurrency,
            'order_ttl_seconds' => $orderTtl,
        ];
        foreach (['create_path', 'query_path'] as $pathKey) {
            if (!empty($configuration[$pathKey])) {
                $normalizedConfiguration[$pathKey] = '/' . ltrim((string) $configuration[$pathKey], '/');
            }
        }

        $existing = Db::name('payment_channels')->where('channel_code', $channelCode)->find();
        $credentialsInput = (array) ($input['credentials'] ?? []);
        $secretKey = trim((string) ($credentialsInput['secret_key'] ?? ''));
        if ($secretKey !== '') {
            $credentials = ['secret_key' => $secretKey];
            $credentialsCiphertext = PaymentCredentialCipher::encrypt(json_encode($credentials, JSON_THROW_ON_ERROR));
        } elseif (is_array($existing) && (string) $existing['credentials_ciphertext'] !== '') {
            $credentialsCiphertext = (string) $existing['credentials_ciphertext'];
        } else {
            throw new RuntimeException('Merchant secret key is required when configuring a channel for the first time.');
        }

        $data = [
            'channel_code' => $channelCode,
            'display_name' => $displayName,
            'enabled' => $enabled ? 1 : 0,
            'allowed_currencies' => json_encode($allowedCurrencies, JSON_THROW_ON_ERROR),
            'merchant_pid' => $merchantPid,
            'credentials_ciphertext' => $credentialsCiphertext,
            'configuration' => json_encode($normalizedConfiguration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (is_array($existing)) {
            Db::name('payment_channels')->where('id', (int) $existing['id'])->update($data);
        } else {
            $data['created_at'] = gmdate('Y-m-d H:i:s');
            Db::name('payment_channels')->insert($data);
        }

        Db::execute(
            'INSERT INTO auth_audit_logs
                (actor_user_id, event_type, target_type, target_id, ip_address, user_agent, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $actorUserId,
                'PAYMENT_CHANNEL_CONFIG_UPDATED',
                'PAYMENT_CHANNEL',
                $channelCode,
                $ipAddress !== '' ? $ipAddress : null,
                mb_substr($userAgent, 0, 255, 'UTF-8'),
                json_encode([
                    'enabled' => $enabled,
                    'allowed_currencies' => $allowedCurrencies,
                    'credentials_rotated' => $secretKey !== '',
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ],
        );

        foreach ($this->list() as $channel) {
            if ($channel['channel_code'] === $channelCode) {
                return $channel;
            }
        }

        throw new RuntimeException('Unable to read the saved payment channel.');
    }

    private static function httpsUrl(string $url, string $label): string
    {
        $parts = parse_url(trim($url));
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException($label . ' must be a valid HTTPS URL.');
        }

        return rtrim(trim($url), '/');
    }

    private static function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(0, strlen($value) - 4)) . substr($value, -4);
    }
}
