<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Tenancy;

use Corvant\Domain\Tenancy\Entities\Tenant;

final class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }
}
