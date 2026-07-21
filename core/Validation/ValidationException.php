<?php

declare(strict_types=1);

namespace Core\Validation;

use RuntimeException;

/**
 * Thrown when validation fails. Carries the field-level error messages.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string[]> $errors */
    public function __construct(private readonly array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct($message, 422);
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }
}
