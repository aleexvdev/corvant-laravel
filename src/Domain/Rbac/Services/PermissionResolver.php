<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\Services;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Ports\RoleRepositoryPort;

final class PermissionResolver
{
    public function __construct(
        private RoleRepositoryPort $roles,
    ) {}

    public function userHasPermission(User $user, ?Tenant $tenant, string $permission): bool
    {
        $userId = $user->id();
        if ($userId === null) {
            return false;
        }

        foreach ($this->roles->rolesForUser($userId) as $role) {
            if ($role->isGlobalSuperAdmin()) {
                return true;
            }

            if (! in_array($permission, $role->permissions(), true)) {
                continue;
            }

            if ($role->tenantId() === null) {
                continue;
            }

            if ($tenant === null || $tenant->id() === null) {
                continue;
            }

            if ($role->tenantId() === $tenant->id()) {
                return true;
            }
        }

        return false;
    }
}
