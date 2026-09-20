<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
});

function registerUser(string $email = 'sessions@example.com', string $password = 'password123'): void
{
    test()->postJson('/auth/register', [
        'email' => $email,
        'password' => $password,
        'name' => 'Sessions User',
    ])->assertCreated();
}

function loginUser(string $email, string $password): string
{
    $response = test()->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    return (string) $response->json('token');
}

it('lists active sessions without exposing bearer tokens', function (): void {
    $email = 'list@example.com';
    $password = 'password123';
    registerUser($email, $password);

    $tokenA = loginUser($email, $password);
    $tokenB = loginUser($email, $password);

    $response = $this->getJson('/sessions', [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    foreach ($data as $entry) {
        expect($entry)->toHaveKeys(['id', 'expires_at', 'is_current'])
            ->and($entry)->not->toHaveKey('token');
    }

    expect(collect($data)->filter(fn (array $entry) => (bool) $entry['is_current'])->count())->toBe(1)
        ->and(collect($data)->reject(fn (array $entry) => (bool) $entry['is_current'])->count())->toBe(1);

    $currentEntry = collect($data)->first(fn (array $entry) => (bool) $entry['is_current']);
    expect($currentEntry['id'])->toBe(hash('sha256', $tokenA));
});

it('revokes a specific session so its token no longer authenticates', function (): void {
    $email = 'revoke-one@example.com';
    $password = 'password123';
    registerUser($email, $password);

    $tokenA = loginUser($email, $password);
    $tokenB = loginUser($email, $password);

    $list = $this->getJson('/sessions', [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();

    $targetId = collect($list->json('data'))
        ->firstWhere('is_current', false)['id'];

    $this->deleteJson('/sessions/'.$targetId, [], [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();

    $this->getJson('/users/me', [
        'Authorization' => 'Bearer '.$tokenB,
    ])->assertUnauthorized();

    $this->getJson('/users/me', [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();
});

it('revokes all sessions except the current one', function (): void {
    $email = 'revoke-others@example.com';
    $password = 'password123';
    registerUser($email, $password);

    $tokenA = loginUser($email, $password);
    $tokenB = loginUser($email, $password);
    loginUser($email, $password);

    $this->deleteJson('/sessions', [], [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();

    $this->getJson('/users/me', [
        'Authorization' => 'Bearer '.$tokenB,
    ])->assertUnauthorized();

    $this->getJson('/users/me', [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();

    $remaining = $this->getJson('/sessions', [
        'Authorization' => 'Bearer '.$tokenA,
    ])->assertOk();

    expect($remaining->json('data'))->toHaveCount(1)
        ->and($remaining->json('data.0.is_current'))->toBeTrue();
});

it('requires authentication for session management endpoints', function (): void {
    $this->getJson('/sessions')->assertUnauthorized();
    $this->deleteJson('/sessions')->assertUnauthorized();
    $this->deleteJson('/sessions/some-id')->assertUnauthorized();
});
