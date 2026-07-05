<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    private const int MIN_CENTAVOS = 0;
    private const int MAX_CENTAVOS = 99_999_999_999;
    private const int MIN_DIVIDE_PARTS = 1;
    private const int MAX_DIVIDE_PARTS = 1000;

    public function __construct(
        private int $centavos,
    ) {
        if ($centavos < self::MIN_CENTAVOS || $centavos > self::MAX_CENTAVOS) {
            throw new InvalidArgumentException(
                "Centavos must be between 0 and 99,999,999,999, got {$centavos}."
            );
        }
    }

    public static function fromCentavos(int $centavos): self
    {
        return new self($centavos);
    }

    public static function fromPesos(float $pesos): self
    {
        if ($pesos < 0) {
            throw new InvalidArgumentException(
                'Peso value must not be negative.'
            );
        }

        $pesosString = rtrim(rtrim(sprintf('%.10f', $pesos), '0'), '.');
        $dotPosition = strpos($pesosString, '.');

        if ($dotPosition !== false) {
            $decimalPlaces = strlen($pesosString) - $dotPosition - 1;

            if ($decimalPlaces > 2) {
                throw new InvalidArgumentException(
                    'Peso value must not have more than 2 decimal places.'
                );
            }
        }

        $centavos = (int) round($pesos * 100);

        return new self($centavos);
    }

    public function toCentavos(): int
    {
        return $this->centavos;
    }

    public function toPesos(): float
    {
        return $this->centavos / 100;
    }

    public function add(Money $other): self
    {
        $result = $this->centavos + $other->centavos;

        if ($result > self::MAX_CENTAVOS) {
            throw new InvalidArgumentException(
                "Addition result exceeds maximum allowed value of 99,999,999,999 centavos."
            );
        }

        return new self($result);
    }

    public function subtract(Money $other): self
    {
        $result = $this->centavos - $other->centavos;

        if ($result < 0) {
            throw new InvalidArgumentException(
                'Subtraction would result in a negative value.'
            );
        }

        return new self($result);
    }

    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException(
                'Multiply factor must not be negative.'
            );
        }

        $result = $this->centavos * $factor;

        if ($result > self::MAX_CENTAVOS) {
            throw new InvalidArgumentException(
                "Multiplication result exceeds maximum allowed value of 99,999,999,999 centavos."
            );
        }

        return new self($result);
    }

    /**
     * Divide a total centavo amount into N parts with remainder distribution.
     *
     * The first R parts (where R = totalCentavos mod parts) receive basePart + 1,
     * and the remaining parts receive basePart.
     *
     * @param int $totalCentavos The total amount in centavos to divide
     * @param int $parts The number of parts to divide into (1–1,000)
     * @return int[] Array of N integers whose sum equals totalCentavos
     */
    public static function divide(int $totalCentavos, int $parts): array
    {
        if ($totalCentavos < 0) {
            throw new InvalidArgumentException(
                'Total centavos must not be negative.'
            );
        }

        if ($parts < self::MIN_DIVIDE_PARTS || $parts > self::MAX_DIVIDE_PARTS) {
            throw new InvalidArgumentException(
                "Parts must be between 1 and 1,000, got {$parts}."
            );
        }

        $basePart = intdiv($totalCentavos, $parts);
        $remainder = $totalCentavos % $parts;

        $result = [];

        for ($i = 0; $i < $parts; $i++) {
            $result[] = $i < $remainder ? $basePart + 1 : $basePart;
        }

        return $result;
    }

    public function format(): string
    {
        $pesos = $this->centavos / 100;

        return '₱' . number_format($pesos, 2, '.', ',');
    }

    public function equals(Money $other): bool
    {
        return $this->centavos === $other->centavos;
    }

    public function greaterThan(Money $other): bool
    {
        return $this->centavos > $other->centavos;
    }

    public function isZero(): bool
    {
        return $this->centavos === 0;
    }
}
