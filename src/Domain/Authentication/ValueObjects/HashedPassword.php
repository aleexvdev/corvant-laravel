<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\ValueObjects;

use InvalidArgumentException;

final readonly class HashedPassword
{
    private string $hash;

    public function __construct(string $hash)
    {
        if ($hash === '') {
            throw new InvalidArgumentException('Hashed password cannot be empty.');
        }

        $this->hash = $hash;
    }

    public function hash(): string
    {
        return $this->hash;
    }
}
