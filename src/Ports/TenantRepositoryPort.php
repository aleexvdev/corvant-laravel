<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Tenancy\Entities\Tenant;

interface TenantRepositoryPort
{
    public function findById(int $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    /**
     * @return list<Tenant>
     */
    public function tenantsForUser(int $userId): array;
}
