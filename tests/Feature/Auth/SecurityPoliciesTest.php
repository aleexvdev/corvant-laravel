<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
});

it('rejects weak passwords when complexity rules are enabled', function (): void {
    config([
        'corvant.password.require_mixed_case' => true,
        'corvant.password.require_numbers' => true,
        'corvant.password.require_symbols' => true,
    ]);

    $this->postJson('/auth/register', [
        'email' => 'weak@example.com',
        'password' => 'password123',
        'name' => 'Weak User',
    ])->assertStatus(422);
});

it('returns 429 when login is rate limited', function (): void {
    config(['corvant.rate_limiting.login_attempts_per_minute' => 2]);

    $payload = ['email' => 'rate@example.com', 'password' => 'wrong'];

    $this->postJson('/auth/login', $payload)->assertUnauthorized();
    $this->postJson('/auth/login', $payload)->assertUnauthorized();
    $this->postJson('/auth/login', $payload)->assertStatus(429);
});

it('returns the same locked response for known and unknown emails', function (): void {
    config(['corvant.account_lockout.max_failed_attempts' => 2]);

    $this->postJson('/auth/register', [
        'email' => 'known-lock@example.com',
        'password' => 'password123',
        'name' => 'Known User',
    ])->assertCreated();

    foreach (['known-lock@example.com', 'unknown-lock@example.com'] as $email) {
        Redis::connection()->flushDB();

        $this->postJson('/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->postJson('/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $locked = $this->postJson('/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertStatus(423);

        expect($locked->json('message'))->toContain('temporarily locked');
    }
});

it('expires sessions after idle timeout and extends them while still active', function (): void {
    config(['corvant.session.ttl_seconds' => 3]);

    $this->postJson('/auth/register', [
        'email' => 'idle@example.com',
        'password' => 'password123',
        'name' => 'Idle User',
    ])->assertCreated();

    $login = $this->postJson('/auth/login', [
        'email' => 'idle@example.com',
        'password' => 'password123',
    ])->assertOk();

    $token = (string) $login->json('token');

    sleep(4);

    $this->getJson('/users/me', [
        'Authorization' => 'Bearer '.$token,
    ])->assertUnauthorized();

    $loginAgain = $this->postJson('/auth/login', [
        'email' => 'idle@example.com',
        'password' => 'password123',
    ])->assertOk();

    $activeToken = (string) $loginAgain->json('token');

    $this->getJson('/users/me', [
        'Authorization' => 'Bearer '.$activeToken,
    ])->assertOk();

    sleep(2);

    $this->getJson('/users/me', [
        'Authorization' => 'Bearer '.$activeToken,
    ])->assertOk();
});
