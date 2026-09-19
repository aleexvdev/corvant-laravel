<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;
use Corvant\Ports\TenantRepositoryPort;

final class EloquentTenantRepository implements TenantRepositoryPort
{
    public function findById(int $id): ?Tenant
    {
        $model = CorvantTenantModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $normalized = strtolower(trim($slug));

        $model = CorvantTenantModel::query()
            ->where('slug', $normalized)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function tenantsForUser(int $userId): array
    {
        return CorvantTenantModel::query()
            ->whereHas('users', fn ($query) => $query->where('corvant_users.id', $userId))
            ->orderBy('name')
            ->get()
            ->map(fn (CorvantTenantModel $model) => $this->toDomain($model))
            ->all();
    }

    private function toDomain(CorvantTenantModel $model): Tenant
    {
        return new Tenant(
            (int) $model->getKey(),
            $model->name,
            new TenantSlug($model->slug),
        );
    }
}
