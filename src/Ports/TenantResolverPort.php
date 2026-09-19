<?php

declare(strict_types=1);

namespace Corvant\Ports;

interface TenantResolverPort
{
    /**
     * Resolve a tenant identifier from request metadata (e.g. HTTP headers).
     *
     * @param array<string, string|null> $headers Lowercase header names mapped to values
     */
    public function resolve(array $headers): ?string;
}
