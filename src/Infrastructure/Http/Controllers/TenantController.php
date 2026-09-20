<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Domain\Tenancy\Exceptions\InvalidTenantSlugException;
use Corvant\Domain\Tenancy\Exceptions\TenantSlugAlreadyExistsException;
use Corvant\Domain\Tenancy\Services\TenancyService;
use Corvant\Domain\Tenancy\Services\TenantProvisioningService;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Infrastructure\Http\Requests\StoreTenantRequest;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Corvant\Ports\RoleRepositoryPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class TenantController extends Controller
{
    public function store(
        StoreTenantRequest $request,
        CurrentUser $currentUser,
        TenantProvisioningService $provisioning,
        RoleRepositoryPort $roles,
    ): JsonResponse {
        $user = $currentUser->get();
        if ($user === null || $user->id() === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $tenant = $provisioning->provision(
                $request->validated('name'),
                $request->validated('slug'),
                $user->id(),
            );
        } catch (InvalidTenantSlugException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (TenantSlugAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $tenantId = $tenant->id();
        $roleNames = $tenantId === null
            ? []
            : array_map(
                static fn ($role) => $role->name(),
                $roles->allForTenant($tenantId),
            );

        return response()->json([
            'tenant' => $this->tenantPayload($tenant),
            'roles' => $roleNames,
        ], 201);
    }

    public function current(CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $currentTenant->get();

        if ($tenant === null) {
            return response()->json([
                'message' => 'No tenant resolved for this request.',
            ], 404);
        }

        return response()->json($this->tenantPayload($tenant));
    }

    public function forUser(int $userId, TenancyService $tenancy): JsonResponse
    {
        $tenants = $tenancy->tenantsForUser($userId);

        return response()->json([
            'data' => array_map(
                fn (Tenant $tenant) => $this->tenantPayload($tenant),
                $tenants,
            ),
        ]);
    }

    /**
     * @return array{id: int|null, name: string, slug: string}
     */
    private function tenantPayload(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id(),
            'name' => $tenant->name(),
            'slug' => $tenant->slug()->value(),
        ];
    }
}
