<?php
declare(strict_types=1);

namespace app\security;

use RuntimeException;

final class RemotePlatformSigner implements PlatformSigner
{
    public function sign(string $keyReference, string $message): string
    {
        $endpoint = trim((string)getenv('AUTH_KMS_SIGN_ENDPOINT'));
        $token = trim((string)getenv('AUTH_KMS_SIGN_TOKEN'));
        if ($endpoint === '' || $token === '') {
            throw new RuntimeException('平台 KMS/HSM 签名服务尚未配置，离线授权签发已关闭。');
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('KMS/HSM 签名服务地址必须使用 HTTPS。');
        }

        $body = json_encode([
            'key_ref' => $keyReference,
            'algorithm' => 'RSA-PSS-SHA256',
            'mgf1_hash' => 'SHA-256',
            'salt_length' => 32,
            'message_base64' => base64_encode($message),
        ], JSON_THROW_ON_ERROR);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];
        $responseBody = '';
        $curl = curl_init($endpoint);
        if ($curl === false) throw new RuntimeException('无法初始化 KMS/HSM 签名请求。');
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody): int {
                    if (strlen($responseBody) + strlen($chunk) > 65536) return 0;
                    $responseBody .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($ok === false) throw new RuntimeException('KMS/HSM 签名请求失败：' . curl_error($curl));
        } finally {
            curl_close($curl);
        }

        try {
            $result = json_decode($responseBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new RuntimeException('KMS/HSM 返回了无效响应。', 0, $exception);
        }
        if ($status < 200 || $status >= 300 || !is_array($result)
            || !is_string($result['signature_base64'] ?? null)) {
            throw new RuntimeException('KMS/HSM 拒绝签名请求或响应缺少签名。');
        }
        $signature = base64_decode($result['signature_base64'], true);
        if (!is_string($signature) || strlen($signature) < 384 || strlen($signature) > 1024) {
            throw new RuntimeException('KMS/HSM 返回的 RSA 签名长度无效。');
        }
        return $signature;
    }
}
