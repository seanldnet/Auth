<?php
declare(strict_types=1);

namespace app\security;

use InvalidArgumentException;
use RuntimeException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

final class PlatformSignatureService
{
    public function __construct(private readonly ?PlatformSigner $signer = null) {}

    public function signAndVerify(string $keyReference, string $publicKeyPem, string $message): string
    {
        $signer = $this->signer ?? new RemotePlatformSigner();
        try {
            $publicKey = PublicKeyLoader::load($publicKeyPem);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('平台离线签名公钥无法解析。', 0, $exception);
        }
        if (!method_exists($publicKey, 'getLength') || $publicKey->getLength() < 3072) {
            throw new InvalidArgumentException('平台离线签名公钥必须为 RSA-3072 或更高强度。');
        }
        $signature = $signer->sign($keyReference, $message);
        $valid = $publicKey
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS)
            ->withMGFHash('sha256')
            ->withSaltLength(32)
            ->verify($message, $signature);
        if (!$valid) {
            throw new RuntimeException('KMS/HSM 签名与已登记的平台公钥不匹配，拒绝签发离线授权。');
        }
        return $signature;
    }
}
