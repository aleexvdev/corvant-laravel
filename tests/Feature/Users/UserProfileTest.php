<?php

declare(strict_types=1);

use Corvant\Adapters\Persistence\CorvantRoleModel;
use Corvant\Adapters\Persistence\CorvantTenantModel;
use Corvant\Adapters\Persistence\CorvantUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
    Mail::fake();
});

/**
 * @return array<string, string>
 */
function profileAuthHeaders(string $email = 'profile@example.com', string $password = 'password123'): array
{
    CorvantUserModel::query()->firstOrCreate(
        ['email' => $email],
        ['password' => Hash::make($password), 'name' => 'Profile User'],
    );

    $login = test()->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);

    return [
        'Authorization' => 'Bearer '.$login->json('token'),
    ];
}

function singleUseTokenForPurpose(string $purpose): string
{
    $keys = Redis::connection()->keys('*corvant:single-use:'.$purpose.':*');
    expect($keys)->not->toBeEmpty();

    $pattern = '/corvant:single-use:'.preg_quote($purpose, '/').':([a-f0-9]+)$/';
    expect(preg_match($pattern, (string) $keys[0], $matches))->toBe(1);

    return $matches[1];
}

it('requires authentication for GET /users/me', function (): void {
    $this->getJson('/users/me')->assertUnauthorized();
});

it('round-trips profile updates via PUT /users/me', function (): void {
    $headers = profileAuthHeaders();

    $this->putJson('/users/me', [
        'name' => 'Updated Name',
        'avatar_url' => 'https://example.com/avatar.png',
        'locale' => 'en',
        'timezone' => 'UTC',
    ], $headers)
        ->assertOk()
        ->assertJson([
            'name' => 'Updated Name',
            'avatar_url' => 'https://example.com/avatar.png',
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);

    $this->getJson('/users/me', $headers)
        ->assertOk()
        ->assertJsonPath('name', 'Updated Name');
});

it('does not change email until email-change confirmation', function (): void {
    $headers = profileAuthHeaders('pending@example.com');

    $this->putJson('/users/me/email', [
        'email' => 'newmail@example.com',
    ], $headers)->assertOk();

    $this->getJson('/users/me', $headers)
        ->assertOk()
        ->assertJsonPath('email', 'pending@example.com')
        ->assertJsonPath('pending_email', 'newmail@example.com');

    $token = singleUseTokenForPurpose('email-change');

    $this->postJson('/users/me/email/confirm', ['token' => $token], $headers)
        ->assertOk()
        ->assertJsonPath('email', 'newmail@example.com')
        ->assertJsonPath('pending_email', null);
});

it('deletes the user and cascades memberships and roles', function (): void {
    $user = CorvantUserModel::query()->create([
        'email' => 'delete-me@example.com',
        'password' => Hash::make('password123'),
        'name' => 'Delete Me',
    ]);

    $tenant = CorvantTenantModel::query()->create(['name' => 'T', 'slug' => 'delete-t']);
    $tenant->users()->attach($user->getKey());

    $role = CorvantRoleModel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'member',
        'permissions' => [],
    ]);
    $user->roles()->attach($role->getKey());

    $login = $this->postJson('/auth/login', [
        'email' => 'delete-me@example.com',
        'password' => 'password123',
    ]);

    $token = $login->json('token');

    $this->deleteJson('/users/me', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect(CorvantUserModel::query()->whereKey($user->getKey())->exists())->toBeFalse();
    expect($tenant->users()->whereKey($user->getKey())->exists())->toBeFalse();
    expect($user->roles()->whereKey($role->getKey())->exists())->toBeFalse();
});
