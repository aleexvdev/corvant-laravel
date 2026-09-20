<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\Exceptions;

use DomainException;

final class RoleNotFoundException extends DomainException
{
    public function __construct(int $roleId)
    {
        parent::__construct(sprintf('Role %d was not found.', $roleId));
    }
}
