<?php

declare(strict_types=1);

namespace Corvant\Adapters\Http;

use Corvant\Ports\TenantResolverPort;

final class HeaderTenantResolver implements TenantResolverPort
{
    public function __construct(
        private string $headerName,
    ) {}

    public function resolve(array $headers): ?string
    {
        $key = strtolower($this->headerName);
        $value = $headers[$key] ?? null;

        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
