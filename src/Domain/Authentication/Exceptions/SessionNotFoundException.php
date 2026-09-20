<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Exceptions;

use DomainException;

final class SessionNotFoundException extends DomainException
{
    public function __construct(string $sessionId)
    {
        parent::__construct(sprintf('Session %s was not found.', $sessionId));
    }
}
