<?php
declare(strict_types=1);

namespace app\payment;

use RuntimeException;

final class GmpayHttpClient
{
    /**
     * @param array<string, scalar|null> $parameters
     * @return array<string, mixed>
     */
    public static function postJson(string $baseUrl, string $path, array $parameters, int $timeout = 12): array
    {
        return self::request($baseUrl, $path, 'POST', $parameters, $timeout);
    }

    /**
     * @param array<string, scalar|null> $parameters
     * @return array<string, mixed>
     */
    public static function get(string $baseUrl, string $path, array $parameters = [], int $timeout = 8): array
    {
        return self::request($baseUrl, $path, 'GET', $parameters, $timeout);
    }

    /**
     * @param array<string, scalar|null> $parameters
     * @return array<string, mixed>
     */
    private static function request(
        string $baseUrl,
        string $path,
        string $method,
        array $parameters,
        int $timeout,
    ): array {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The PHP cURL extension is required for payment gateway calls.');
        }

        $parts = parse_url($baseUrl);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Payment gateway base URL must use HTTPS.');
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        if ($method === 'GET' && $parameters !== []) {
            $url .= '?' . http_build_query($parameters);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize payment gateway request.');
        }

        $headers = ['Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $body = json_encode($parameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
            $options[CURLOPT_HTTPHEADER] = [
                'Accept: application/json',
                'Content-Type: application/json',
            ];
        }

        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($raw === false) {
            throw new RuntimeException('Payment gateway network error: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Payment gateway returned HTTP ' . $status);
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Payment gateway returned invalid JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Payment gateway response must be a JSON object.');
        }

        return $decoded;
    }
}