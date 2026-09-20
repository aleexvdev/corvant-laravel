<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\Exceptions;

use DomainException;

final class RoleTenantMismatchException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Role does not belong to the current tenant context.');
    }
}
