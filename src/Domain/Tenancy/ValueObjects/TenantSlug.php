<?php

declare(strict_types=1);

namespace Corvant\Domain\Tenancy\ValueObjects;

use Corvant\Domain\Tenancy\Exceptions\InvalidTenantSlugException;

final readonly class TenantSlug
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $normalized) !== 1) {
            throw new InvalidTenantSlugException($value);
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
