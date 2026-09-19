<?php

declare(strict_types=1);

namespace Corvant\Domain\Tenancy\Exceptions;

use DomainException;

final class InvalidTenantSlugException extends DomainException
{
    public function __construct(string $slug)
    {
        parent::__construct(sprintf('Invalid tenant slug: %s', $slug));
    }
}
