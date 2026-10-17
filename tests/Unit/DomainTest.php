<?php

namespace Tests\Unit;

use App\Domain\Money;
use App\Domain\ShiftCalculator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DomainTest extends TestCase
{
    public function test_money_uses_integer_minor_units_and_rejects_mixed_currency(): void
    {
        self::assertSame(125, (new Money(100, 'RON'))->plus(new Money(25, 'RON'))->minor);
        $this->expectException(InvalidArgumentException::class);
        (new Money(100, 'RON'))->plus(new Money(1, 'EUR'));
    }

    public function test_shift_duration_uses_elapsed_utc_time_and_deducts_break(): void
    {
        self::assertSame(450, ShiftCalculator::paidMinutes(new DateTimeImmutable('2026-10-24T21:00:00Z'), new DateTimeImmutable('2026-10-25T05:00:00Z'), 30));
    }
}
