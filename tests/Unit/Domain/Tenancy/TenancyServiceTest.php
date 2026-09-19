<?php

declare(strict_types=1);

use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Domain\Tenancy\Services\TenancyService;
use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;
use Corvant\Ports\TenantRepositoryPort;

function makeTenancyService(TenantRepositoryPort $tenants): TenancyService
{
    return new TenancyService($tenants);
}

it('resolves current tenant by numeric identifier', function (): void {
    $tenant = new Tenant(5, 'Acme', new TenantSlug('acme'));

    $repo = Mockery::mock(TenantRepositoryPort::class);
    $repo->shouldReceive('findById')->once()->with(5)->andReturn($tenant);

    $service = makeTenancyService($repo);

    expect($service->currentTenantFor('5')?->id())->toBe(5);
});

it('resolves current tenant by slug identifier', function (): void {
    $tenant = new Tenant(2, 'Beta', new TenantSlug('beta'));

    $repo = Mockery::mock(TenantRepositoryPort::class);
    $repo->shouldReceive('findBySlug')->once()->with('beta')->andReturn($tenant);

    $service = makeTenancyService($repo);

    expect($service->currentTenantFor('beta')?->slug()->value())->toBe('beta');
});

it('returns null when identifier is blank', function (): void {
    $repo = Mockery::mock(TenantRepositoryPort::class);
    $repo->shouldReceive('findById')->never();
    $repo->shouldReceive('findBySlug')->never();

    $service = makeTenancyService($repo);

    expect($service->currentTenantFor('   '))->toBeNull();
});

it('lists tenants for a user', function (): void {
    $tenants = [
        new Tenant(1, 'One', new TenantSlug('one')),
        new Tenant(2, 'Two', new TenantSlug('two')),
    ];

    $repo = Mockery::mock(TenantRepositoryPort::class);
    $repo->shouldReceive('tenantsForUser')->once()->with(99)->andReturn($tenants);

    $service = makeTenancyService($repo);

    expect($service->tenantsForUser(99))->toHaveCount(2);
});
