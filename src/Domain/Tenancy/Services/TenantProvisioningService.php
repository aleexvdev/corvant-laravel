<?php

declare(strict_types=1);

namespace Corvant\Domain\Tenancy\Services;

use Corvant\Domain\Rbac\Entities\Role;
use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Domain\Tenancy\Exceptions\TenantSlugAlreadyExistsException;
use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;
use Corvant\Ports\RoleRepositoryPort;
use Corvant\Ports\TenantRepositoryPort;

final class TenantProvisioningService
{
    /** @var list<string> */
    public const STRUCTURAL_ROLE_NAMES = ['Owner', 'Member', 'Auditor', 'Guest'];

    public function __construct(
        private TenantRepositoryPort $tenants,
        private RoleRepositoryPort $roles,
    ) {}

    public function provision(string $name, string $slug, int $creatorUserId): Tenant
    {
        $tenantSlug = new TenantSlug($slug);

        if ($this->tenants->findBySlug($tenantSlug->value()) !== null) {
            throw new TenantSlugAlreadyExistsException($tenantSlug);
        }

        $tenant = $this->tenants->save(Tenant::create($name, $tenantSlug));
        $tenantId = $tenant->id();

        if ($tenantId === null) {
            throw new \LogicException('Provisioned tenant must have an id.');
        }

        $this->tenants->addMember($tenantId, $creatorUserId);

        $ownerRoleId = null;

        foreach (self::STRUCTURAL_ROLE_NAMES as $roleName) {
            $role = $this->roles->save(Role::create($roleName, $tenantId, []));
            if (strcasecmp($roleName, 'Owner') === 0) {
                $ownerRoleId = $role->id();
            }
        }

        if ($ownerRoleId === null) {
            throw new \LogicException('Owner role was not created during provisioning.');
        }

        $this->roles->attachRoleToUser($creatorUserId, $ownerRoleId);

        return $tenant;
    }
}
