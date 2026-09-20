<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Middleware;

use Closure;
use Corvant\Domain\Rbac\Services\PermissionResolver;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PermissionMiddleware
{
    public function __construct(
        private CurrentUser $currentUser,
        private CurrentTenant $currentTenant,
        private PermissionResolver $permissions,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $this->currentUser->get();
        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated. Apply corvant.authenticate middleware before permission checks.',
            ], 401);
        }

        if (! $this->permissions->userHasPermission($user, $this->currentTenant->get(), $permission)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
