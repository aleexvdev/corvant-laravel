<?php

declare(strict_types=1);

namespace Corvant\Domain\Tenancy\Services;

use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Ports\TenantRepositoryPort;

final class TenancyService
{
    public function __construct(
        private TenantRepositoryPort $tenants,
    ) {}

    public function currentTenantFor(string $resolvedIdentifier): ?Tenant
    {
        $identifier = trim($resolvedIdentifier);
        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            return $this->tenants->findById((int) $identifier);
        }

        return $this->tenants->findBySlug(strtolower($identifier));
    }

    /**
     * @return list<Tenant>
     */
    public function tenantsForUser(int $userId): array
    {
        return $this->tenants->tenantsForUser($userId);
    }
}
