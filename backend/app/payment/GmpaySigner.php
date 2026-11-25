<?php
declare(strict_types=1);

namespace app\payment;

final class GmpaySigner
{
    /** @param array<string, scalar|null> $parameters */
    public static function canonicalize(array $parameters): string
    {
        unset($parameters['signature']);
        ksort($parameters, SORT_STRING);

        $pairs = [];
        foreach ($parameters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('GMPay signature fields must be scalar values.');
            }
            $pairs[] = $key . '=' . (string) $value;
        }

        return implode('&', $pairs);
    }

    /** @param array<string, scalar|null> $parameters */
    public static function sign(array $parameters, string $secretKey): string
    {
        if ($secretKey === '') {
            throw new \InvalidArgumentException('GMPay secret key is required.');
        }

        return hash_hmac('sha256', self::canonicalize($parameters), $secretKey);
    }

    /** @param array<string, scalar|null> $parameters */
    public static function verify(array $parameters, string $secretKey): bool
    {
        $provided = $parameters['signature'] ?? null;
        return is_string($provided)
            && hash_equals(self::sign($parameters, $secretKey), strtolower($provided));
    }
}