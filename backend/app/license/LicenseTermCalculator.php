<?php
declare(strict_types=1);

namespace app\license;

use DateTimeImmutable;
use RuntimeException;

final class LicenseTermCalculator
{
    public static function expiresAt(DateTimeImmutable $start, string $unit, ?int $value): ?DateTimeImmutable
    {
        if ($unit === 'PERMANENT') return null;
        if ($value === null || $value < 1 || $value > 1200) throw new RuntimeException('授权套餐时长配置异常。');
        if ($unit === 'DAY') return $start->modify('+' . $value . ' days');
        $months = match ($unit) {
            'MONTH' => $value,
            'YEAR' => $value * 12,
            default => throw new RuntimeException('授权套餐时长单位无效。'),
        };
        $target = $start->modify('first day of this month')->modify('+' . $months . ' months');
        $day = min((int)$start->format('d'), (int)$target->format('t'));
        return $target->setDate((int)$target->format('Y'), (int)$target->format('m'), $day)
            ->setTime((int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s'));
    }
}
