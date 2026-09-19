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
