<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Exceptions;

use DomainException;

final class InvalidOrExpiredTokenException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired token.');
    }
}
