<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DeletionConstraintException extends RuntimeException
{
    private string $entityType;
    private string $dependentType;
    private int $dependentCount;

    public function __construct(string $entityType, string $dependentType, int $dependentCount, int $code = 0, ?\Throwable $previous = null)
    {
        $this->entityType = $entityType;
        $this->dependentType = $dependentType;
        $this->dependentCount = $dependentCount;

        $message = "Cannot delete {$entityType}: {$dependentCount} active {$dependentType} linked to it.";

        parent::__construct($message, $code, $previous);
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getDependentType(): string
    {
        return $this->dependentType;
    }

    public function getDependentCount(): int
    {
        return $this->dependentCount;
    }

    public function getHttpStatusCode(): int
    {
        return 409;
    }
}
