<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Exceptions;

use DomainException;

final class MfaChallengeRequiredException extends DomainException
{
    public function __construct(
        private readonly string $challengeToken,
    ) {
        parent::__construct('Multi-factor authentication is required to complete login.');
    }

    public function challengeToken(): string
    {
        return $this->challengeToken;
    }
}
