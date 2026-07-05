<?php

declare(strict_types=1);

namespace App\Traits;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use InvalidArgumentException;

trait HasMonetaryFields
{
    /**
     * Declare which model columns are monetary.
     *
     * @return array<int, string> Array of column name strings
     */
    abstract protected function monetaryFields(): array;

    /**
     * Register the MoneyCast for all declared monetary fields.
     *
     * Called automatically by Eloquent during model initialization
     * via the initializeXxx() naming convention.
     */
    protected function initializeHasMonetaryFields(): void
    {
        foreach ($this->monetaryFields() as $field) {
            $this->mergeCasts([
                $field => MoneyCast::class,
            ]);
        }
    }

    /**
     * Get a monetary field as a Money value object.
     */
    public function getMoneyAttribute(string $field): ?Money
    {
        $value = $this->attributes[$field] ?? null;

        if ($value === null) {
            return null;
        }

        return Money::fromCentavos((int) $value);
    }

    /**
     * Set a monetary field from a Money instance, integer, or null.
     *
     * @throws InvalidArgumentException For negative/overflow integers or unsupported types
     */
    public function setMoneyAttribute(string $field, Money|int|null $value): void
    {
        if ($value === null) {
            $this->attributes[$field] = null;

            return;
        }

        if ($value instanceof Money) {
            $this->attributes[$field] = $value->toCentavos();

            return;
        }

        if (is_int($value)) {
            if ($value < 0 || $value > 99_999_999_999) {
                throw new InvalidArgumentException(
                    "Monetary value must be between 0 and 99,999,999,999 centavos, got {$value}."
                );
            }

            $this->attributes[$field] = $value;

            return;
        }
    }
}
