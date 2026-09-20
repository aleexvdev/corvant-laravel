<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\Services;

use Corvant\Domain\Rbac\Entities\Role;
use Corvant\Domain\Rbac\Exceptions\RoleAlreadyExistsException;
use Corvant\Domain\Rbac\Exceptions\RoleNotFoundException;
use Corvant\Domain\Rbac\Exceptions\RoleTenantMismatchException;
use Corvant\Domain\Audit\AuditEvents;
use Corvant\Domain\Rbac\ValueObjects\PermissionName;
use Corvant\Ports\AuditLoggerPort;
use Corvant\Ports\RoleRepositoryPort;

final class RoleService
{
    public function __construct(
        private RoleRepositoryPort $roles,
        private AuditLoggerPort $auditLogger,
    ) {}

    /**
     * @return list<Role>
     */
    public function listRolesForTenant(?int $tenantId): array
    {
        return $this->roles->allForTenant($tenantId);
    }

    /**
     * @param list<string> $permissions
     */
    public function createRole(?int $tenantId, string $name, array $permissions): Role
    {
        $this->assertPermissions($permissions);

        if ($this->roles->findByNameAndTenant($name, $tenantId) !== null) {
            throw new RoleAlreadyExistsException($name);
        }

        return $this->roles->save(Role::create($name, $tenantId, $permissions));
    }

    /**
     * @param list<string> $permissions
     */
    public function updateRole(int $roleId, ?int $tenantId, string $name, array $permissions): Role
    {
        $this->assertPermissions($permissions);

        $role = $this->roles->findById($roleId);
        if ($role === null) {
            throw new RoleNotFoundException($roleId);
        }

        $this->assertRoleInTenantContext($role, $tenantId);

        $existing = $this->roles->findByNameAndTenant($name, $tenantId);
        if ($existing !== null && $existing->id() !== $roleId) {
            throw new RoleAlreadyExistsException($name);
        }

        $updated = new Role($roleId, $name, $role->tenantId(), $permissions);

        return $this->roles->save($updated);
    }

    public function deleteRole(int $roleId, ?int $tenantId): void
    {
        $role = $this->roles->findById($roleId);
        if ($role === null) {
            throw new RoleNotFoundException($roleId);
        }

        $this->assertRoleInTenantContext($role, $tenantId);

        $this->roles->delete($roleId);
    }

    public function assignRole(int $userId, int $roleId, ?int $tenantId): void
    {
        $role = $this->roles->findById($roleId);
        if ($role === null) {
            throw new RoleNotFoundException($roleId);
        }

        $this->assertRoleInTenantContext($role, $tenantId);

        $this->roles->attachRoleToUser($userId, $roleId);

        $this->auditLogger->log(AuditEvents::ROLE_ASSIGNED, $userId, $tenantId, [
            'role_id' => $roleId,
            'role_name' => $role->name(),
        ]);
    }

    public function revokeRole(int $userId, int $roleId, ?int $tenantId): void
    {
        $role = $this->roles->findById($roleId);
        if ($role === null) {
            throw new RoleNotFoundException($roleId);
        }

        $this->assertRoleInTenantContext($role, $tenantId);

        $this->roles->detachRoleFromUser($userId, $roleId);

        $this->auditLogger->log(AuditEvents::ROLE_REVOKED, $userId, $tenantId, [
            'role_id' => $roleId,
            'role_name' => $role->name(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function distinctPermissionsForTenant(?int $tenantId): array
    {
        $permissions = [];

        foreach ($this->roles->allForTenant($tenantId) as $role) {
            foreach ($role->permissions() as $permission) {
                $permissions[$permission] = true;
            }
        }

        $names = array_keys($permissions);
        sort($names);

        return $names;
    }

    /**
     * @param list<string> $permissions
     */
    private function assertPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            new PermissionName($permission);
        }
    }

    private function assertRoleInTenantContext(Role $role, ?int $tenantId): void
    {
        if ($role->isGlobalSuperAdmin()) {
            throw new RoleTenantMismatchException();
        }

        if ($tenantId === null || $role->tenantId() !== $tenantId) {
            throw new RoleTenantMismatchException();
        }
    }
}
