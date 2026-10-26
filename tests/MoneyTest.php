<?php

declare(strict_types=1);

namespace ExactLedger\Tests;

use ExactLedger\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_a_float_cast_loses_a_unit_that_scale_keeps(): void
    {
        $this->assertSame(28, (int) (0.29 * 100));       // the bug this class exists for
        $this->assertSame(2900, Money::scale('0.29'));
        $this->assertSame(2900, Money::scale(0.29));
    }

    public function test_many_small_amounts_sum_exactly(): void
    {
        $this->assertNotSame(1.0, array_sum(array_fill(0, 10, 0.1)));
        $this->assertSame(Money::scale('1'), Money::sum(array_fill(0, 10, '0.1')));
    }

    public function test_rounds_half_up_past_the_ledger_precision(): void
    {
        $this->assertSame(12346, Money::scale('1.23455'));
        $this->assertSame(12345, Money::scale('1.23454'));
        $this->assertSame(-12346, Money::scale('-1.23455'));
    }

    public function test_round_trips_to_a_decimal_column_string(): void
    {
        $this->assertSame('120.0000', Money::toString(Money::scale('120')));
        $this->assertSame('-0.0500', Money::toString(Money::scale('-0.05')));
        $this->assertTrue(Money::equals('120.0000', 120));
    }

    public function test_display_precision_comes_from_the_currency(): void
    {
        $this->assertSame('12,345', Money::format('12345', 'JPY'));
        $this->assertSame('1.250', Money::format('1.25', 'KWD'));
        $this->assertSame('1.25', Money::format('1.25', 'USD'));
    }

    public function test_refuses_anything_that_is_not_a_plain_decimal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::scale('$12');
    }
}
