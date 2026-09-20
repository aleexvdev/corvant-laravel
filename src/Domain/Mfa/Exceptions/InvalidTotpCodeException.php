<?php

declare(strict_types=1);

namespace Corvant\Domain\Mfa\Exceptions;

use DomainException;

final class InvalidTotpCodeException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid TOTP code format.');
    }
}
