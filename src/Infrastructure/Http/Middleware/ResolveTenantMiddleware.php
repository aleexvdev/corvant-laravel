<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Middleware;

use Closure;
use Corvant\Domain\Tenancy\Services\TenancyService;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Corvant\Ports\TenantResolverPort;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantMiddleware
{
    public function __construct(
        private TenantResolverPort $resolver,
        private TenancyService $tenancy,
        private CurrentTenant $currentTenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = $values[0] ?? null;
        }

        $identifier = $this->resolver->resolve($headers);
        if ($identifier !== null) {
            $this->currentTenant->set($this->tenancy->currentTenantFor($identifier));
        }

        return $next($request);
    }
}
