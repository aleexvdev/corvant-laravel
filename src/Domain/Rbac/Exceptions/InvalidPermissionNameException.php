<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\Exceptions;

use DomainException;

final class InvalidPermissionNameException extends DomainException
{
    public function __construct(string $permission)
    {
        parent::__construct(sprintf('Invalid permission name: %s', $permission));
    }
}
