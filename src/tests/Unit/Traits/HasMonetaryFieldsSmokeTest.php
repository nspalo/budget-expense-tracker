<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasMonetaryFieldsSmokeTest extends TestCase
{
    private MoneyCast $cast;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new MoneyCast();
    }

    private function fakeModel(): Model
    {
        return new class extends Model {};
    }

    #[Test]
    public function get_returns_null_for_null_value(): void
    {
        $result = $this->cast->get($this->fakeModel(), 'amount_centavos', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function get_returns_money_for_non_null_integer(): void
    {
        $result = $this->cast->get($this->fakeModel(), 'amount_centavos', 15000, []);

        $this->assertInstanceOf(Money::class, $result);
        $this->assertSame(15000, $result->toCentavos());
    }

    #[Test]
    public function get_returns_money_for_string_integer_from_database(): void
    {
        // Database drivers sometimes return numeric strings
        $result = $this->cast->get($this->fakeModel(), 'amount_centavos', '25000', []);

        $this->assertInstanceOf(Money::class, $result);
        $this->assertSame(25000, $result->toCentavos());
    }

    #[Test]
    public function set_returns_centavos_for_money_instance(): void
    {
        $money = Money::fromCentavos(5000);

        $result = $this->cast->set($this->fakeModel(), 'amount_centavos', $money, []);

        $this->assertSame(5000, $result);
    }

    #[Test]
    public function set_returns_value_directly_for_valid_integer(): void
    {
        $result = $this->cast->set($this->fakeModel(), 'amount_centavos', 12345, []);

        $this->assertSame(12345, $result);
    }

    #[Test]
    public function set_returns_null_for_null_value(): void
    {
        $result = $this->cast->set($this->fakeModel(), 'amount_centavos', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_accepts_zero_integer(): void
    {
        $result = $this->cast->set($this->fakeModel(), 'amount_centavos', 0, []);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function set_accepts_maximum_integer(): void
    {
        $result = $this->cast->set($this->fakeModel(), 'amount_centavos', 99_999_999_999, []);

        $this->assertSame(99_999_999_999, $result);
    }

    #[Test]
    public function set_throws_for_negative_integer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between 0 and 99,999,999,999');

        $this->cast->set($this->fakeModel(), 'amount_centavos', -1, []);
    }

    #[Test]
    public function set_throws_for_overflow_integer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between 0 and 99,999,999,999');

        $this->cast->set($this->fakeModel(), 'amount_centavos', 100_000_000_000, []);
    }

    #[Test]
    public function set_throws_for_string_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type');

        $this->cast->set($this->fakeModel(), 'amount_centavos', '5000', []);
    }

    #[Test]
    public function set_throws_for_float_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type');

        $this->cast->set($this->fakeModel(), 'amount_centavos', 50.00, []);
    }

    #[Test]
    public function set_throws_for_array_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type');

        $this->cast->set($this->fakeModel(), 'amount_centavos', [5000], []);
    }

    #[Test]
    public function set_throws_for_object_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type');

        $this->cast->set($this->fakeModel(), 'amount_centavos', new \stdClass(), []);
    }
}
