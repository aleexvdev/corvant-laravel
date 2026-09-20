<?php

declare(strict_types=1);

use Corvant\Adapters\Persistence\CorvantTenantModel;
use Corvant\Adapters\Persistence\CorvantUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
});

function createCorvantUser(string $email = 'member@example.com'): CorvantUserModel
{
    return CorvantUserModel::query()->create([
        'email' => $email,
        'password' => Hash::make('password123'),
        'name' => 'Member',
    ]);
}

function attachUserToTenant(CorvantUserModel $user, string $name, string $slug): CorvantTenantModel
{
    $tenant = CorvantTenantModel::query()->create([
        'name' => $name,
        'slug' => $slug,
    ]);

    $tenant->users()->attach($user->getKey());

    return $tenant;
}

it('returns 404 for GET /tenants/current without tenant header', function (): void {
    $this->getJson('/tenants/current')->assertNotFound();
});

it('returns 404 for GET /tenants/current with unknown tenant header', function (): void {
    $this->getJson('/tenants/current', [
        'X-Tenant-ID' => '99999',
    ])->assertNotFound();
});

it('returns current tenant when X-Tenant-ID header is valid', function (): void {
    $user = createCorvantUser();
    $tenant = attachUserToTenant($user, 'Acme Inc', 'acme-inc');

    $this->getJson('/tenants/current', [
        'X-Tenant-ID' => (string) $tenant->getKey(),
    ])
        ->assertOk()
        ->assertJson([
            'id' => $tenant->getKey(),
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
        ]);
});

it('lists tenants for a user via GET /users/{id}/tenants', function (): void {
    $user = createCorvantUser('multi@example.com');
    attachUserToTenant($user, 'Tenant A', 'tenant-a');
    attachUserToTenant($user, 'Tenant B', 'tenant-b');

    $response = $this->getJson('/users/'.$user->getKey().'/tenants');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['slug' => 'tenant-a'])
        ->assertJsonFragment(['slug' => 'tenant-b']);
});

it('returns empty list when user has no tenant memberships', function (): void {
    $user = createCorvantUser('lonely@example.com');

    $this->getJson('/users/'.$user->getKey().'/tenants')
        ->assertOk()
        ->assertJson(['data' => []]);
});

/**
 * @return array<string, string>
 */
function tenancyAuthHeaders(CorvantUserModel $user): array
{
    $login = test()->postJson('/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    return [
        'Authorization' => 'Bearer '.$login->json('token'),
    ];
}

it('creates a tenant with structural roles via POST /tenants', function (): void {
    $user = createCorvantUser('provisioner@example.com');
    $headers = tenancyAuthHeaders($user);

    $response = $this->postJson('/tenants', [
        'name' => 'Provisioned Inc',
        'slug' => 'provisioned-inc',
    ], $headers);

    $response->assertCreated()
        ->assertJsonPath('tenant.slug', 'provisioned-inc')
        ->assertJsonCount(4, 'roles');

    expect($response->json('roles'))->toEqualCanonicalizing([
        'Owner',
        'Member',
        'Auditor',
        'Guest',
    ]);

    $tenantId = $response->json('tenant.id');

    $this->getJson('/users/'.$user->getKey().'/tenants')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'provisioned-inc']);

    $tenantHeaders = array_merge($headers, ['X-Tenant-ID' => (string) $tenantId]);

    $roles = $this->getJson('/roles', $tenantHeaders)
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->json('data');

    $ownerRoleId = collect($roles)->firstWhere('name', 'Owner')['id'];

    expect($user->roles()->whereKey($ownerRoleId)->exists())->toBeTrue();
});

it('rejects duplicate tenant slug on POST /tenants with 422', function (): void {
    $user = createCorvantUser('dup-slug@example.com');
    $headers = tenancyAuthHeaders($user);

    $this->postJson('/tenants', [
        'name' => 'First Org',
        'slug' => 'same-slug',
    ], $headers)->assertCreated();

    $this->postJson('/tenants', [
        'name' => 'Second Org',
        'slug' => 'same-slug',
    ], $headers)->assertStatus(422);
});
