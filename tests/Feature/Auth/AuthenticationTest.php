<?php

declare(strict_types=1);

use Corvant\Adapters\Persistence\CorvantUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
});

it('registers a user via POST /auth/register', function (): void {
    $response = $this->postJson('/auth/register', [
        'email' => 'register@example.com',
        'password' => 'password123',
        'name' => 'Register User',
    ]);

    $response->assertCreated()
        ->assertJson([
            'email' => 'register@example.com',
            'name' => 'Register User',
        ]);

    expect(CorvantUserModel::query()->where('email', 'register@example.com')->exists())->toBeTrue();
});

it('rejects duplicate registration', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'dup@example.com',
        'password' => 'password123',
        'name' => 'First',
    ])->assertCreated();

    $this->postJson('/auth/register', [
        'email' => 'dup@example.com',
        'password' => 'password123',
        'name' => 'Second',
    ])->assertStatus(422);
});

it('logs in and returns a session token', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'login@example.com',
        'password' => 'password123',
        'name' => 'Login User',
    ])->assertCreated();

    $response = $this->postJson('/auth/login', [
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'expires_at']);
});

it('returns 401 for invalid login credentials', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'badlogin@example.com',
        'password' => 'password123',
        'name' => 'User',
    ]);

    $this->postJson('/auth/login', [
        'email' => 'badlogin@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized();
});

it('logs out and revokes the session token', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'logout@example.com',
        'password' => 'password123',
        'name' => 'Logout User',
    ]);

    $login = $this->postJson('/auth/login', [
        'email' => 'logout@example.com',
        'password' => 'password123',
    ]);

    $token = $login->json('token');

    $this->postJson('/auth/logout', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect(Redis::connection()->exists('corvant:session:'.$token))->toBe(0);
});
