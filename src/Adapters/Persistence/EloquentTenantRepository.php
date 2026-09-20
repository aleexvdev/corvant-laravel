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

    public function save(Tenant $tenant): Tenant
    {
        if ($tenant->id() !== null) {
            $model = CorvantTenantModel::query()->findOrFail($tenant->id());
            $model->fill([
                'name' => $tenant->name(),
                'slug' => $tenant->slug()->value(),
            ]);
            $model->save();

            return $this->toDomain($model);
        }

        $model = CorvantTenantModel::query()->create([
            'name' => $tenant->name(),
            'slug' => $tenant->slug()->value(),
        ]);

        return $this->toDomain($model);
    }

    public function addMember(int $tenantId, int $userId): void
    {
        $model = CorvantTenantModel::query()->findOrFail($tenantId);
        $model->users()->syncWithoutDetaching([$userId]);
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
