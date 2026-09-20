<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Rbac\Services\RoleService;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class PermissionController extends Controller
{
    public function __construct(
        private RoleService $roles,
        private CurrentTenant $currentTenant,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = $this->currentTenant->get();
        if ($tenant === null || $tenant->id() === null) {
            return response()->json(['message' => 'No tenant resolved for this request.'], 404);
        }

        return response()->json([
            'data' => $this->roles->distinctPermissionsForTenant($tenant->id()),
        ]);
    }
}
