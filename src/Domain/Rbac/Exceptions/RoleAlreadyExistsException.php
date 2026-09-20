<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\Exceptions;

use DomainException;

final class RoleAlreadyExistsException extends DomainException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('A role named %s already exists for this tenant.', $name));
    }
}
