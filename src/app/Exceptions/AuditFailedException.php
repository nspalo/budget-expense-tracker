<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class AuditFailedException extends RuntimeException
{
    public function __construct(string $message = 'Failed to persist audit log entry.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
