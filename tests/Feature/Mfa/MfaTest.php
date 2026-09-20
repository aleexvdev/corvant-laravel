<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
});

function registerAndLogin(string $email = 'mfa-flow@example.com', string $password = 'password123'): string
{
    test()->postJson('/auth/register', [
        'email' => $email,
        'password' => $password,
        'name' => 'MFA User',
    ])->assertCreated();

    $login = test()->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    return (string) $login->json('token');
}

function totpCodeForSecret(string $secret): string
{
    return (new Google2FA())->getCurrentOtp($secret);
}

it('completes the full MFA lifecycle including recovery codes', function (): void {
    $password = 'password123';
    $email = 'lifecycle@example.com';

    $token = registerAndLogin($email, $password);

    $enable = $this->postJson('/mfa/totp/enable', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $secret = (string) $enable->json('secret');
    expect($enable->json('otpauth_uri'))->toContain('otpauth://');

    $this->postJson('/mfa/totp/confirm', [
        'code' => totpCodeForSecret($secret),
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $recovery = $this->getJson('/mfa/recovery-codes', [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $codes = $recovery->json('recovery_codes');
    expect($codes)->toHaveCount(10);

    $this->postJson('/auth/logout', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $challengeLogin = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()
        ->assertJson([
            'mfa_required' => true,
        ]);

    $challengeToken = (string) $challengeLogin->json('challenge_token');
    expect($challengeLogin->json('token'))->toBeNull();

    $wrongVerify = $this->postJson('/mfa/totp/verify', [
        'challenge_token' => $challengeToken,
        'code' => '000000',
    ]);
    $wrongVerify->assertUnauthorized();

    $sessionAfterTotp = $this->postJson('/mfa/totp/verify', [
        'challenge_token' => $challengeToken,
        'code' => totpCodeForSecret($secret),
    ])->assertOk()
        ->assertJsonStructure(['token', 'expires_at']);

    $sessionToken = (string) $sessionAfterTotp->json('token');

    $this->postJson('/auth/logout', [], [
        'Authorization' => 'Bearer '.$sessionToken,
    ])->assertOk();

    $recoveryChallenge = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    $recoveryChallengeToken = (string) $recoveryChallenge->json('challenge_token');

    $recoveryLogin = $this->postJson('/mfa/recovery-codes/use', [
        'challenge_token' => $recoveryChallengeToken,
        'code' => $codes[0],
    ])->assertOk()
        ->assertJsonStructure(['token', 'expires_at']);

    $reuse = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    $this->postJson('/mfa/recovery-codes/use', [
        'challenge_token' => (string) $reuse->json('challenge_token'),
        'code' => $codes[0],
    ])->assertUnauthorized();

    $disableToken = (string) $recoveryLogin->json('token');

    $this->postJson('/mfa/totp/disable', [
        'password' => $password,
    ], [
        'Authorization' => 'Bearer '.$disableToken,
    ])->assertOk();

    $this->postJson('/auth/logout', [], [
        'Authorization' => 'Bearer '.$disableToken,
    ])->assertOk();

    $directLogin = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()
        ->assertJsonStructure(['token', 'expires_at'])
        ->assertJsonMissing(['mfa_required' => true]);

    expect($directLogin->json('mfa_required'))->toBeNull();
});

it('requires authentication for MFA management endpoints', function (): void {
    $this->postJson('/mfa/totp/enable')->assertUnauthorized();
    $this->postJson('/mfa/totp/confirm', ['code' => '123456'])->assertUnauthorized();
    $this->postJson('/mfa/totp/disable', ['password' => 'secret'])->assertUnauthorized();
    $this->getJson('/mfa/recovery-codes')->assertUnauthorized();
});
