<?php

declare(strict_types=1);

use Corvant\Domain\Audit\AuditEvents;
use Corvant\Domain\Rbac\Entities\Role;
use Corvant\Domain\Rbac\Services\RoleService;
use Corvant\Ports\AuditLoggerPort;
use Corvant\Ports\RoleRepositoryPort;

it('logs role assignment with tenant context and role metadata', function (): void {
    $role = new Role(9, 'editor', 4, ['post:edit']);

    $roles = Mockery::mock(RoleRepositoryPort::class);
    $roles->shouldReceive('findById')->once()->with(9)->andReturn($role);
    $roles->shouldReceive('attachRoleToUser')->once()->with(2, 9);

    $audit = Mockery::mock(AuditLoggerPort::class);
    $audit->shouldReceive('log')->once()->with(AuditEvents::ROLE_ASSIGNED, 2, 4, [
        'role_id' => 9,
        'role_name' => 'editor',
    ]);

    $service = new RoleService($roles, $audit);
    $service->assignRole(2, 9, 4);
});

it('logs role revocation with tenant context and role metadata', function (): void {
    $role = new Role(11, 'viewer', 5, ['post:read']);

    $roles = Mockery::mock(RoleRepositoryPort::class);
    $roles->shouldReceive('findById')->once()->with(11)->andReturn($role);
    $roles->shouldReceive('detachRoleFromUser')->once()->with(3, 11);

    $audit = Mockery::mock(AuditLoggerPort::class);
    $audit->shouldReceive('log')->once()->with(AuditEvents::ROLE_REVOKED, 3, 5, [
        'role_id' => 11,
        'role_name' => 'viewer',
    ]);

    $service = new RoleService($roles, $audit);
    $service->revokeRole(3, 11, 5);
});
