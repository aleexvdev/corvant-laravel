<?php

declare(strict_types=1);

use Corvant\Adapters\Persistence\CorvantRoleModel;
use Corvant\Adapters\Persistence\CorvantTenantModel;
use Corvant\Adapters\Persistence\CorvantUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
});

function rbacUser(string $email = 'rbac@example.com'): CorvantUserModel
{
    return CorvantUserModel::query()->create([
        'email' => $email,
        'password' => Hash::make('password123'),
        'name' => 'RBAC User',
    ]);
}

function rbacTenant(CorvantUserModel $user, string $slug = 'rbac-tenant'): CorvantTenantModel
{
    $tenant = CorvantTenantModel::query()->create([
        'name' => 'RBAC Tenant',
        'slug' => $slug,
    ]);
    $tenant->users()->attach($user->getKey());

    return $tenant;
}

/**
 * @return array<string, string>
 */
function rbacAuthHeaders(CorvantUserModel $user, CorvantTenantModel $tenant): array
{
    $login = test()->postJson('/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    return [
        'Authorization' => 'Bearer '.$login->json('token'),
        'X-Tenant-ID' => (string) $tenant->getKey(),
    ];
}

it('performs role CRUD for the current tenant', function (): void {
    $user = rbacUser();
    $tenant = rbacTenant($user);
    $headers = rbacAuthHeaders($user, $tenant);

    $create = $this->postJson('/roles', [
        'name' => 'accountant',
        'permissions' => ['invoice:create', 'invoice:read'],
    ], $headers)->assertCreated();

    $roleId = $create->json('id');

    $this->getJson('/roles', $headers)
        ->assertOk()
        ->assertJsonFragment(['name' => 'accountant']);

    $this->putJson('/roles/'.$roleId, [
        'name' => 'lead-accountant',
        'permissions' => ['invoice:create'],
    ], $headers)->assertOk()->assertJsonFragment(['name' => 'lead-accountant']);

    $this->getJson('/permissions', $headers)
        ->assertOk()
        ->assertJson(['data' => ['invoice:create']]);

    $this->deleteJson('/roles/'.$roleId, [], $headers)->assertOk();
});

it('assigns and revokes roles for a user', function (): void {
    $actor = rbacUser('actor@example.com');
    $member = rbacUser('member@example.com');
    $tenant = rbacTenant($actor, 'assign-tenant');
    $headers = rbacAuthHeaders($actor, $tenant);

    $roleId = $this->postJson('/roles', [
        'name' => 'member',
        'permissions' => ['users:read'],
    ], $headers)->json('id');

    $this->postJson('/users/'.$member->getKey().'/roles', [
        'role_id' => $roleId,
    ], $headers)->assertOk();

    expect(CorvantRoleModel::query()->find($roleId)?->users()->whereKey($member->getKey())->exists())->toBeTrue();

    $this->deleteJson('/users/'.$member->getKey().'/roles/'.$roleId, [], $headers)->assertOk();

    expect(CorvantRoleModel::query()->find($roleId)?->users()->whereKey($member->getKey())->exists())->toBeFalse();
});

it('authorizes protected routes when permission middleware passes', function (): void {
    $user = rbacUser('allowed@example.com');
    $tenant = rbacTenant($user, 'allowed-tenant');
    $headers = rbacAuthHeaders($user, $tenant);

    $roleId = $this->postJson('/roles', [
        'name' => 'billing',
        'permissions' => ['invoice:create'],
    ], $headers)->json('id');

    $this->postJson('/users/'.$user->getKey().'/roles', [
        'role_id' => $roleId,
    ], $headers)->assertOk();

    $this->getJson('/_test/rbac-protected', $headers)
        ->assertOk()
        ->assertJson(['authorized' => true]);
});

it('returns 403 when permission middleware fails', function (): void {
    $user = rbacUser('denied@example.com');
    $tenant = rbacTenant($user, 'denied-tenant');
    $headers = rbacAuthHeaders($user, $tenant);

    $roleId = $this->postJson('/roles', [
        'name' => 'viewer',
        'permissions' => ['users:read'],
    ], $headers)->json('id');

    $this->postJson('/users/'.$user->getKey().'/roles', [
        'role_id' => $roleId,
    ], $headers)->assertOk();

    $this->getJson('/_test/rbac-protected', $headers)->assertForbidden();
});

it('returns 401 for protected routes without authentication', function (): void {
    $user = rbacUser('anon@example.com');
    $tenant = rbacTenant($user, 'anon-tenant');

    $this->getJson('/_test/rbac-protected', [
        'X-Tenant-ID' => (string) $tenant->getKey(),
    ])->assertUnauthorized();
});
