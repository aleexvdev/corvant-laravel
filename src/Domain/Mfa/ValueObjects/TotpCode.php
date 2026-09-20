<?php

declare(strict_types=1);

namespace Corvant\Domain\Mfa\ValueObjects;

use Corvant\Domain\Mfa\Exceptions\InvalidTotpCodeException;

final readonly class TotpCode
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (preg_match('/^\d{6}$/', $trimmed) !== 1) {
            throw new InvalidTotpCodeException();
        }

        $this->value = $trimmed;
    }

    public function value(): string
    {
        return $this->value;
    }
}
