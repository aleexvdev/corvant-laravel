<?php

declare(strict_types=1);

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

function singleUseTokenFromRedis(string $purpose): string
{
    $keys = Redis::connection()->keys('*corvant:single-use:'.$purpose.':*');
    expect($keys)->not->toBeEmpty();

    $pattern = '/corvant:single-use:'.preg_quote($purpose, '/').':([a-f0-9]+)$/';
    expect(preg_match($pattern, (string) $keys[0], $matches))->toBe(1);

    return $matches[1];
}

it('returns the same forgot-password response for existing and unknown emails', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'exists@example.com',
        'password' => 'password123',
        'name' => 'Exists',
    ]);

    $existing = $this->postJson('/auth/forgot-password', [
        'email' => 'exists@example.com',
    ]);

    $unknown = $this->postJson('/auth/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    expect($existing->json())->toBe($unknown->json())
        ->and($existing->status())->toBe($unknown->status());
});

it('resets the password using a single-use token', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'reset@example.com',
        'password' => 'password123',
        'name' => 'Reset User',
    ]);

    $this->postJson('/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();

    $token = singleUseTokenFromRedis('password-reset');

    $this->postJson('/auth/reset-password', [
        'token' => $token,
        'password' => 'newpassword99',
    ])->assertOk();

    $this->postJson('/auth/login', [
        'email' => 'reset@example.com',
        'password' => 'newpassword99',
    ])->assertOk();
});

it('verifies email with a single-use token', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'verify@example.com',
        'password' => 'password123',
        'name' => 'Verify User',
    ]);

    $login = $this->postJson('/auth/login', [
        'email' => 'verify@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/auth/resend-verification', [], [
        'Authorization' => 'Bearer '.$login->json('token'),
    ])->assertOk();

    $token = singleUseTokenFromRedis('email-verification');

    $this->postJson('/auth/verify-email', ['token' => $token])->assertOk();

    expect(CorvantUserModel::query()->where('email', 'verify@example.com')->first()?->email_verified_at)->not->toBeNull();
});

it('requires authentication for resend-verification', function (): void {
    $this->postJson('/auth/resend-verification')->assertUnauthorized();
});

it('refreshes a session token and invalidates the previous one', function (): void {
    $this->postJson('/auth/register', [
        'email' => 'refresh@example.com',
        'password' => 'password123',
        'name' => 'Refresh User',
    ]);

    $login = $this->postJson('/auth/login', [
        'email' => 'refresh@example.com',
        'password' => 'password123',
    ]);

    $oldToken = $login->json('token');

    $refreshed = $this->postJson('/auth/refresh', [], [
        'Authorization' => 'Bearer '.$oldToken,
    ])->assertOk();

    $newToken = $refreshed->json('token');
    expect($newToken)->not->toBe($oldToken);

    expect(Redis::connection()->exists('corvant:session:'.$oldToken))->toBe(0);

    $this->postJson('/auth/refresh', [], [
        'Authorization' => 'Bearer '.$oldToken,
    ])->assertUnauthorized();
});
