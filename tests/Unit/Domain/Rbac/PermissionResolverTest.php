<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Domain\Rbac\Entities\Role;
use Corvant\Domain\Rbac\Services\PermissionResolver;
use Corvant\Domain\Tenancy\Entities\Tenant;
use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;
use Corvant\Ports\RoleRepositoryPort;

function makeUser(int $id = 1): User
{
    return new User(
        $id,
        new Email('user@example.com'),
        new HashedPassword('hash'),
        'User',
    );
}

function makeTenant(int $id = 10): Tenant
{
    return new Tenant($id, 'Acme', new TenantSlug('acme'));
}

it('grants permission when user has a matching tenant-scoped role', function (): void {
    $roles = Mockery::mock(RoleRepositoryPort::class);
    $roles->shouldReceive('rolesForUser')->once()->with(1)->andReturn([
        new Role(5, 'accountant', 10, ['invoice:create']),
    ]);

    $resolver = new PermissionResolver($roles);

    expect($resolver->userHasPermission(makeUser(), makeTenant(), 'invoice:create'))->toBeTrue();
});

it('denies permission when the role belongs to another tenant', function (): void {
    $roles = Mockery::mock(RoleRepositoryPort::class);
    $roles->shouldReceive('rolesForUser')->once()->andReturn([
        new Role(5, 'accountant', 99, ['invoice:create']),
    ]);

    $resolver = new PermissionResolver($roles);

    expect($resolver->userHasPermission(makeUser(), makeTenant(10), 'invoice:create'))->toBeFalse();
});

it('denies permission when user lacks the permission on the role', function (): void {
    $roles = Mockery::mock(RoleRepositoryPort::class);
    $roles->shouldReceive('rolesForUser')->once()->andReturn([
        new Role(5, 'member', 10, ['users:read']),
    ]);

    $resolver = new PermissionResolver($roles);

    expect($resolver->userHasPermission(makeUser(), makeTenant(), 'invoice:create'))->toBeFalse();
});

it('bypasses checks for the global SuperAdmin role', function (): void {
    $roles = Mockery::mock(RoleRepositoryPort::class);
    $roles->shouldReceive('rolesForUser')->once()->andReturn([
        new Role(1, 'SuperAdmin', null, []),
    ]);

    $resolver = new PermissionResolver($roles);

    expect($resolver->userHasPermission(makeUser(), null, 'anything:goes'))->toBeTrue();
});
