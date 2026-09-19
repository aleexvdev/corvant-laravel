<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence\Scopes;

use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope for consumer Eloquent models that include a tenant_id column.
 * Applies only when a current tenant is bound for the request.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(CurrentTenant::class)->get();

        if ($tenant === null || $tenant->id() === null) {
            return;
        }

        $builder->where($model->getTable().'.tenant_id', $tenant->id());
    }
}
