<?php

declare(strict_types=1);

use Corvant\Domain\Rbac\Entities\Role;
use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Domain\Tenancy\Exceptions\TenantSlugAlreadyExistsException;
use Corvant\Domain\Tenancy\Services\TenantProvisioningService;
use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;
use Corvant\Ports\RoleRepositoryPort;
use Corvant\Ports\TenantRepositoryPort;

it('provisions a tenant with four structural roles and assigns Owner to the creator', function (): void {
    $slug = new TenantSlug('new-co');

    $tenants = Mockery::mock(TenantRepositoryPort::class);
    $tenants->shouldReceive('findBySlug')->once()->with('new-co')->andReturn(null);
    $tenants->shouldReceive('save')->once()->andReturn(
        Tenant::create('New Co', $slug)->withId(7),
    );
    $tenants->shouldReceive('addMember')->once()->with(7, 42);

    $roles = Mockery::mock(RoleRepositoryPort::class);
    $roles->shouldReceive('save')->times(4)->andReturnUsing(function (Role $role) {
        $id = match ($role->name()) {
            'Owner' => 100,
            'Member' => 101,
            'Auditor' => 102,
            'Guest' => 103,
            default => 199,
        };

        return $role->withId($id);
    });
    $roles->shouldReceive('attachRoleToUser')->once()->with(42, 100);

    $service = new TenantProvisioningService($tenants, $roles);
    $tenant = $service->provision('New Co', 'new-co', 42);

    expect($tenant->id())->toBe(7);
});

it('throws when the tenant slug already exists', function (): void {
    $slug = new TenantSlug('taken');
    $existing = Tenant::create('Taken', $slug)->withId(1);

    $tenants = Mockery::mock(TenantRepositoryPort::class);
    $tenants->shouldReceive('findBySlug')->once()->andReturn($existing);

    $service = new TenantProvisioningService($tenants, Mockery::mock(RoleRepositoryPort::class));

    $service->provision('Another', 'taken', 1);
})->throws(TenantSlugAlreadyExistsException::class);
