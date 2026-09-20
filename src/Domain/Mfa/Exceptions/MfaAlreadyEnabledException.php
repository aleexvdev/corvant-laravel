<?php

declare(strict_types=1);

namespace Corvant\Domain\Mfa\Exceptions;

use DomainException;

final class MfaAlreadyEnabledException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Multi-factor authentication is already enabled for this account.');
    }
}
