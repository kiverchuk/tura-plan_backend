<?php

namespace App\Domain;

use DateTimeImmutable;

final class ShiftCalculator
{
    /** UTC instants include DST correctly; break minutes are deducted once. */
    public static function paidMinutes(DateTimeImmutable $startUtc, DateTimeImmutable $endUtc, int $breakMinutes = 0): int
    {
        if ($endUtc <= $startUtc) {
            throw new \InvalidArgumentException('Shift end must be after start.');
        }
        $minutes = intdiv($endUtc->getTimestamp() - $startUtc->getTimestamp(), 60) - $breakMinutes;
        if ($breakMinutes < 0 || $minutes < 0 || $minutes > 24 * 60) {
            throw new \InvalidArgumentException('Shift duration is invalid.');
        }

        return $minutes;
    }
}
