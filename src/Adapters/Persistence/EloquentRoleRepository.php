<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Corvant\Domain\Rbac\Entities\Role;
use Corvant\Ports\RoleRepositoryPort;

final class EloquentRoleRepository implements RoleRepositoryPort
{
    public function findById(int $id): ?Role
    {
        $model = CorvantRoleModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByNameAndTenant(string $name, ?int $tenantId): ?Role
    {
        $query = CorvantRoleModel::query()->where('name', $name);

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        $model = $query->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function allForTenant(?int $tenantId): array
    {
        $query = CorvantRoleModel::query()->orderBy('name');

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        return $query
            ->get()
            ->map(fn (CorvantRoleModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(Role $role): Role
    {
        if ($role->id() !== null) {
            $model = CorvantRoleModel::query()->findOrFail($role->id());
            $model->fill([
                'name' => $role->name(),
                'tenant_id' => $role->tenantId(),
                'permissions' => $role->permissions(),
            ]);
            $model->save();

            return $this->toDomain($model);
        }

        $model = CorvantRoleModel::query()->create([
            'name' => $role->name(),
            'tenant_id' => $role->tenantId(),
            'permissions' => $role->permissions(),
        ]);

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        CorvantRoleModel::query()->whereKey($id)->delete();
    }

    public function rolesForUser(int $userId): array
    {
        return CorvantRoleModel::query()
            ->whereHas('users', fn ($query) => $query->where('corvant_users.id', $userId))
            ->get()
            ->map(fn (CorvantRoleModel $model) => $this->toDomain($model))
            ->all();
    }

    public function attachRoleToUser(int $userId, int $roleId): void
    {
        $user = CorvantUserModel::query()->findOrFail($userId);
        $user->roles()->syncWithoutDetaching([$roleId]);
    }

    public function detachRoleFromUser(int $userId, int $roleId): void
    {
        $user = CorvantUserModel::query()->findOrFail($userId);
        $user->roles()->detach($roleId);
    }

    private function toDomain(CorvantRoleModel $model): Role
    {
        /** @var list<string> $permissions */
        $permissions = $model->permissions ?? [];

        return new Role(
            (int) $model->getKey(),
            $model->name,
            $model->tenant_id !== null ? (int) $model->tenant_id : null,
            $permissions,
        );
    }
}
