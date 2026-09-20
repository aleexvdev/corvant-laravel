<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\ValueObjects;

use DateTimeImmutable;

final readonly class MfaChallenge
{
    public function __construct(
        private string $token,
        private int $userId,
        private DateTimeImmutable $expiresAt,
    ) {}

    public function token(): string
    {
        return $this->token;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
