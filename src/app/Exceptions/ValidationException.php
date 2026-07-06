<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @var array<string, list<string>> */
    private array $errors;

    /**
     * @param array<string, list<string>> $errors Field → messages array
     */
    public function __construct(array $errors, int $code = 0, ?\Throwable $previous = null)
    {
        $this->errors = $errors;

        $message = $this->buildMessage($errors);

        parent::__construct($message, $code, $previous);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function withMessages(array $errors): self
    {
        return new self($errors);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function buildMessage(array $errors): string
    {
        $firstField = array_key_first($errors);

        if ($firstField === null) {
            return 'Validation failed.';
        }

        return $errors[$firstField][0] ?? 'Validation failed.';
    }
}
