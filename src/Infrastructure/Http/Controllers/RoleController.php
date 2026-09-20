<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Rbac\Entities\Role;
use Corvant\Domain\Rbac\Exceptions\InvalidPermissionNameException;
use Corvant\Domain\Rbac\Exceptions\RoleAlreadyExistsException;
use Corvant\Domain\Rbac\Exceptions\RoleNotFoundException;
use Corvant\Domain\Rbac\Exceptions\RoleTenantMismatchException;
use Corvant\Domain\Rbac\Services\RoleService;
use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class RoleController extends Controller
{
    public function __construct(
        private RoleService $roles,
        private CurrentTenant $currentTenant,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = $this->requireTenant();

        return response()->json([
            'data' => array_map(
                fn (Role $role) => $this->rolePayload($role),
                $this->roles->listRolesForTenant($tenant->id()),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'string'],
        ]);

        try {
            $role = $this->roles->createRole(
                $tenant->id(),
                $validated['name'],
                $validated['permissions'],
            );
        } catch (InvalidPermissionNameException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RoleAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->rolePayload($role), 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $tenant = $this->requireTenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'string'],
        ]);

        try {
            $role = $this->roles->updateRole(
                $id,
                $tenant->id(),
                $validated['name'],
                $validated['permissions'],
            );
        } catch (InvalidPermissionNameException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RoleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RoleAlreadyExistsException|RoleTenantMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->rolePayload($role));
    }

    public function destroy(int $id): JsonResponse
    {
        $tenant = $this->requireTenant();

        try {
            $this->roles->deleteRole($id, $tenant->id());
        } catch (RoleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RoleTenantMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Role deleted.']);
    }

    public function assignRole(int $userId, Request $request): JsonResponse
    {
        $tenant = $this->requireTenant();

        $validated = $request->validate([
            'role_id' => ['required', 'integer'],
        ]);

        try {
            $this->roles->assignRole($userId, (int) $validated['role_id'], $tenant->id());
        } catch (RoleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RoleTenantMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Role assigned.']);
    }

    public function revokeRole(int $userId, int $roleId): JsonResponse
    {
        $tenant = $this->requireTenant();

        try {
            $this->roles->revokeRole($userId, $roleId, $tenant->id());
        } catch (RoleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RoleTenantMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Role revoked.']);
    }

    /**
     * @return array{id: int|null, name: string, tenant_id: int|null, permissions: list<string>}
     */
    private function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id(),
            'name' => $role->name(),
            'tenant_id' => $role->tenantId(),
            'permissions' => $role->permissions(),
        ];
    }

    private function requireTenant(): Tenant
    {
        $tenant = $this->currentTenant->get();
        if ($tenant === null || $tenant->id() === null) {
            abort(404, 'No tenant resolved for this request.');
        }

        return $tenant;
    }
}
