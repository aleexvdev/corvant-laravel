<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\ValueObjects;

use Corvant\Domain\Rbac\Exceptions\InvalidPermissionNameException;

final readonly class PermissionName
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || preg_match('/^[a-z0-9][a-z0-9_-]*:[a-z0-9][a-z0-9_-]*$/', $normalized) !== 1) {
            throw new InvalidPermissionNameException($value);
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
