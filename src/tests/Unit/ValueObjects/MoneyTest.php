<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function it_creates_from_centavos_with_valid_value(): void
    {
        $money = Money::fromCentavos(15000);

        $this->assertSame(15000, $money->toCentavos());
    }

    #[Test]
    public function it_creates_from_centavos_at_minimum_boundary(): void
    {
        $money = Money::fromCentavos(0);

        $this->assertSame(0, $money->toCentavos());
    }

    #[Test]
    public function it_creates_from_centavos_at_maximum_boundary(): void
    {
        $money = Money::fromCentavos(99_999_999_999);

        $this->assertSame(99_999_999_999, $money->toCentavos());
    }

    #[Test]
    public function it_rejects_negative_centavos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCentavos(-1);
    }

    #[Test]
    public function it_rejects_centavos_exceeding_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCentavos(100_000_000_000);
    }

    #[Test]
    public function it_creates_from_pesos_with_valid_value(): void
    {
        $money = Money::fromPesos(1500.00);

        $this->assertSame(150000, $money->toCentavos());
    }

    #[Test]
    public function it_creates_from_pesos_with_centavos(): void
    {
        $money = Money::fromPesos(25.50);

        $this->assertSame(2550, $money->toCentavos());
    }

    #[Test]
    public function it_rejects_pesos_with_more_than_2_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('more than 2 decimal places');

        Money::fromPesos(10.123);
    }

    #[Test]
    public function it_rejects_negative_pesos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromPesos(-5.00);
    }

    #[Test]
    public function it_converts_to_pesos_correctly(): void
    {
        $money = Money::fromCentavos(150075);

        $this->assertSame(1500.75, $money->toPesos());
    }

    #[Test]
    public function it_adds_two_money_instances(): void
    {
        $a = Money::fromCentavos(1000);
        $b = Money::fromCentavos(2500);

        $result = $a->add($b);

        $this->assertSame(3500, $result->toCentavos());
        // Ensure immutability
        $this->assertSame(1000, $a->toCentavos());
        $this->assertSame(2500, $b->toCentavos());
    }

    #[Test]
    public function it_throws_on_addition_overflow(): void
    {
        $a = Money::fromCentavos(99_999_999_999);
        $b = Money::fromCentavos(1);

        $this->expectException(InvalidArgumentException::class);

        $a->add($b);
    }

    #[Test]
    public function it_subtracts_two_money_instances(): void
    {
        $a = Money::fromCentavos(5000);
        $b = Money::fromCentavos(2000);

        $result = $a->subtract($b);

        $this->assertSame(3000, $result->toCentavos());
    }

    #[Test]
    public function it_throws_on_negative_subtraction_result(): void
    {
        $a = Money::fromCentavos(1000);
        $b = Money::fromCentavos(2000);

        $this->expectException(InvalidArgumentException::class);

        $a->subtract($b);
    }

    #[Test]
    public function it_multiplies_by_integer_factor(): void
    {
        $money = Money::fromCentavos(1500);

        $result = $money->multiply(3);

        $this->assertSame(4500, $result->toCentavos());
    }

    #[Test]
    public function it_throws_on_multiplication_overflow(): void
    {
        $money = Money::fromCentavos(50_000_000_000);

        $this->expectException(InvalidArgumentException::class);

        $money->multiply(3);
    }

    #[Test]
    public function it_divides_evenly(): void
    {
        $result = Money::divide(9000, 3);

        $this->assertSame([3000, 3000, 3000], $result);
        $this->assertSame(9000, array_sum($result));
    }

    #[Test]
    public function it_distributes_remainder_to_first_parts(): void
    {
        $result = Money::divide(10, 3);

        // 10 / 3 = base 3, remainder 1 → first gets 4, rest get 3
        $this->assertSame([4, 3, 3], $result);
        $this->assertSame(10, array_sum($result));
    }

    #[Test]
    public function it_divides_one_centavo_into_three_parts(): void
    {
        $result = Money::divide(1, 3);

        $this->assertSame([1, 0, 0], $result);
        $this->assertSame(1, array_sum($result));
    }

    #[Test]
    public function it_divides_zero_into_parts(): void
    {
        $result = Money::divide(0, 5);

        $this->assertSame([0, 0, 0, 0, 0], $result);
        $this->assertSame(0, array_sum($result));
    }

    #[Test]
    public function it_rejects_divide_with_zero_parts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::divide(100, 0);
    }

    #[Test]
    public function it_rejects_divide_with_parts_exceeding_1000(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::divide(100, 1001);
    }

    #[Test]
    public function it_rejects_divide_with_negative_total(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::divide(-100, 5);
    }

    #[Test]
    public function it_formats_with_peso_sign_and_thousands_separator(): void
    {
        $money = Money::fromCentavos(123456789);

        $this->assertSame('₱1,234,567.89', $money->format());
    }

    #[Test]
    public function it_formats_zero(): void
    {
        $money = Money::fromCentavos(0);

        $this->assertSame('₱0.00', $money->format());
    }

    #[Test]
    public function it_formats_small_amount(): void
    {
        $money = Money::fromCentavos(50);

        $this->assertSame('₱0.50', $money->format());
    }

    #[Test]
    public function it_checks_equality(): void
    {
        $a = Money::fromCentavos(1000);
        $b = Money::fromCentavos(1000);
        $c = Money::fromCentavos(2000);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    #[Test]
    public function it_checks_greater_than(): void
    {
        $a = Money::fromCentavos(2000);
        $b = Money::fromCentavos(1000);

        $this->assertTrue($a->greaterThan($b));
        $this->assertFalse($b->greaterThan($a));
        $this->assertFalse($a->greaterThan(Money::fromCentavos(2000)));
    }

    #[Test]
    public function it_checks_is_zero(): void
    {
        $this->assertTrue(Money::fromCentavos(0)->isZero());
        $this->assertFalse(Money::fromCentavos(1)->isZero());
    }

    #[Test]
    public function it_satisfies_round_trip_property(): void
    {
        $original = Money::fromCentavos(150075);
        $pesos = $original->toPesos();
        $roundTripped = Money::fromPesos($pesos);

        $this->assertTrue($original->equals($roundTripped));
    }
}
