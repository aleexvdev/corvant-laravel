<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\ValueObjects;

use Corvant\Domain\Authentication\Exceptions\InvalidEmailException;

final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailException($value);
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
