<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Domain\Tenancy\Services\TenancyService;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class TenantController extends Controller
{
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
