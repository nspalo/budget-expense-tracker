<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Money|null, int|null>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * Cast the given value from the database to a Money value object.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::fromCentavos((int) $value);
    }

    /**
     * Prepare the given value for storage in the database.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException For negative/overflow integers or unsupported types
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->toCentavos();
        }

        if (is_int($value)) {
            if ($value < 0 || $value > 99_999_999_999) {
                throw new InvalidArgumentException(
                    "Monetary value must be between 0 and 99,999,999,999 centavos, got {$value}."
                );
            }

            return $value;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Unsupported type for monetary field "%s": expected Money, int, or null, got %s.',
                $key,
                get_debug_type($value),
            )
        );
    }
}
