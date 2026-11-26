<?php
declare(strict_types=1);

namespace tests;

use app\license\LicenseTermCalculator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LicenseTermCalculatorTest extends TestCase
{
    public function testPermanentPlanNeverExpires(): void
    {
        $start = new DateTimeImmutable('2026-11-26 10:30:00', new DateTimeZone('UTC'));
        self::assertNull(LicenseTermCalculator::expiresAt($start, 'PERMANENT', null));
    }

    public function testDayPlanUsesExactCalendarDays(): void
    {
        $start = new DateTimeImmutable('2026-11-26 10:30:00', new DateTimeZone('UTC'));
        self::assertSame('2026-11-29 10:30:00', LicenseTermCalculator::expiresAt($start, 'DAY', 3)?->format('Y-m-d H:i:s'));
    }

    public function testMonthPlanClampsAtEndOfShorterMonth(): void
    {
        $start = new DateTimeImmutable('2026-01-31 10:30:00', new DateTimeZone('UTC'));
        self::assertSame('2026-02-28 10:30:00', LicenseTermCalculator::expiresAt($start, 'MONTH', 1)?->format('Y-m-d H:i:s'));
    }

    public function testLeapDayYearPlanClampsToFebruaryEnd(): void
    {
        $start = new DateTimeImmutable('2024-02-29 10:30:00', new DateTimeZone('UTC'));
        self::assertSame('2025-02-28 10:30:00', LicenseTermCalculator::expiresAt($start, 'YEAR', 1)?->format('Y-m-d H:i:s'));
    }

    public function testRejectsInvalidDuration(): void
    {
        $this->expectException(RuntimeException::class);
        LicenseTermCalculator::expiresAt(new DateTimeImmutable('now'), 'MONTH', 0);
    }
}
