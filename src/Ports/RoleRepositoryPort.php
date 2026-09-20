<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Rbac\Entities\Role;

interface RoleRepositoryPort
{
    public function findById(int $id): ?Role;

    public function findByNameAndTenant(string $name, ?int $tenantId): ?Role;

    /**
     * @return list<Role>
     */
    public function allForTenant(?int $tenantId): array;

    public function save(Role $role): Role;

    public function delete(int $id): void;

    /**
     * @return list<Role>
     */
    public function rolesForUser(int $userId): array;

    public function attachRoleToUser(int $userId, int $roleId): void;

    public function detachRoleFromUser(int $userId, int $roleId): void;
}
