<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class EntityNotFoundException extends RuntimeException
{
    private string $entityType;

    public function __construct(string $entityType = 'Entity', int $code = 0, ?\Throwable $previous = null)
    {
        $this->entityType = $entityType;

        parent::__construct("{$entityType} not found.", $code, $previous);
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
