<?php
declare(strict_types=1);

namespace AuthPlatform\LicenseSdk;

use InvalidArgumentException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use RuntimeException;
use Throwable;

final class LicenseClient
{
    private readonly object $privateKey;
    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $appId,
        private readonly string $keyId,
        string $privateKeyPem,
        private readonly int $timeoutSeconds = 10,
    ) {
        if (!preg_match('/^app_[a-fA-F0-9]{24}$/', $appId)) {
            throw new InvalidArgumentException('A valid software App ID is required.');
        }
        if (!preg_match('/^key_[a-f0-9]{16}$/', $keyId)) {
            throw new InvalidArgumentException('A valid signing Key ID is required.');
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('The API base URL must be an absolute HTTPS URL.');
        }
        if ($timeoutSeconds < 1 || $timeoutSeconds > 120) {
            throw new InvalidArgumentException('Timeout must be between 1 and 120 seconds.');
        }

        try {
            $key = PublicKeyLoader::load($privateKeyPem);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Unable to load the RSA private key PEM.', 0, $exception);
        }
        if (!method_exists($key, 'getLength') || !method_exists($key, 'sign') || $key->getLength() < 3072) {
            throw new InvalidArgumentException('RSA-3072 or stronger private key is required.');
        }

        $this->privateKey = $key;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** @return array<string,mixed> */
    public function activate(string $licenseKey, string $deviceFingerprint, ?string $deviceLabel = null): array
    {
        return $this->post('/api/license/activate', [
            'app_id' => $this->appId,
            'license_key' => $licenseKey,
            'device_fingerprint' => $deviceFingerprint,
            'device_label' => $deviceLabel,
        ]);
    }

    /** @return array<string,mixed> */
    public function verify(string $licenseKey, string $deviceFingerprint): array
    {
        return $this->post('/api/license/verify', [
            'app_id' => $this->appId,
            'license_key' => $licenseKey,
            'device_fingerprint' => $deviceFingerprint,
        ]);
    }

    /** @return array<string,mixed> */
    public function changeDevice(
        string $licenseKey,
        string $currentDeviceFingerprint,
        string $newDeviceFingerprint,
        ?string $deviceLabel = null,
    ): array {
        return $this->post('/api/license/change-device', [
            'app_id' => $this->appId,
            'license_key' => $licenseKey,
            'current_device_fingerprint' => $currentDeviceFingerprint,
            'new_device_fingerprint' => $newDeviceFingerprint,
            'device_label' => $deviceLabel,
        ]);
    }

    /** @return array<string,mixed> */
    public function unbind(string $licenseKey, string $deviceFingerprint): array
    {
        return $this->post('/api/license/unbind', [
            'app_id' => $this->appId,
            'license_key' => $licenseKey,
            'device_fingerprint' => $deviceFingerprint,
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function post(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(32));
        $canonical = self::buildCanonicalMessage('POST', $path, $timestamp, $nonce, $body);
        $signature = $this->privateKey
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')
            ->withSaltLength(32)
            ->sign($canonical);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-App-Id: ' . $this->appId,
            'X-Key-Id: ' . $this->keyId,
            'X-Timestamp: ' . $timestamp,
            'X-Nonce: ' . $nonce,
            'X-Signature: ' . base64_encode($signature),
        ];

        $responseBody = '';
        $curl = curl_init($this->baseUrl . $path);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize the HTTP transport.');
        }
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_CONNECTTIMEOUT => min($this->timeoutSeconds, 5),
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody): int {
                    if (strlen($responseBody) + strlen($chunk) > 1_048_576) {
                        return 0;
                    }
                    $responseBody .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            if ($ok === false) {
                throw new RuntimeException('License API transport failed: ' . curl_error($curl));
            }
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }

        try {
            $envelope = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new LicenseApiException($statusCode, 'License API returned invalid JSON.', $exception);
        }
        if (!is_array($envelope) || $statusCode < 200 || $statusCode >= 300
            || ($envelope['success'] ?? false) !== true || !is_array($envelope['data'] ?? null)) {
            $message = is_array($envelope) && is_string($envelope['message'] ?? null)
                ? $envelope['message']
                : 'License API request failed.';
            throw new LicenseApiException($statusCode, $message);
        }
        return $envelope['data'];
    }

    public static function buildCanonicalMessage(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body,
    ): string {
        if (!str_starts_with($path, '/') || str_contains($path, '?')) {
            throw new InvalidArgumentException('Signed path must start with / and must not contain a query string.');
        }
        return strtoupper($method) . "\n"
            . $path . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . hash('sha256', $body);
    }
}
