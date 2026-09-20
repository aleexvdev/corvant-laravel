<?php

declare(strict_types=1);

namespace Corvant\Domain\Mfa\Entities;

final readonly class StoredRecoveryCode
{
    public function __construct(
        private int $id,
        private string $codeHash,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function codeHash(): string
    {
        return $this->codeHash;
    }
}
