<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Exceptions;

use DomainException;

final class AccountLockedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This account is temporarily locked due to too many failed login attempts.');
    }
}
