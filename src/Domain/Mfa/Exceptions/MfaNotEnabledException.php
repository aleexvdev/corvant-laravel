<?php

declare(strict_types=1);

namespace Corvant\Domain\Mfa\Exceptions;

use DomainException;

final class MfaNotEnabledException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Multi-factor authentication is not enabled for this account.');
    }
}
