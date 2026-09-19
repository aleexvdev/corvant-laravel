<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence\Concerns;

use Corvant\Adapters\Persistence\Scopes\TenantScope;

/**
 * Add to a consumer-owned Eloquent model with a tenant_id column to scope queries
 * to the tenant resolved for the current request.
 */
trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
