<?php

declare(strict_types=1);

namespace Corvant\Domain\Tenancy\Exceptions;

use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;
use DomainException;

final class TenantSlugAlreadyExistsException extends DomainException
{
    public function __construct(TenantSlug $slug)
    {
        parent::__construct(sprintf('A tenant with slug %s already exists.', $slug->value()));
    }
}
