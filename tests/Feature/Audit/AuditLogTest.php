<?php

declare(strict_types=1);

use Corvant\Adapters\Persistence\CorvantAuditLogModel;
use Corvant\Adapters\Persistence\CorvantRoleModel;
use Corvant\Adapters\Persistence\CorvantTenantModel;
use Corvant\Adapters\Persistence\CorvantUserModel;
use Corvant\Domain\Audit\AuditEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::connection()->flushDB();
});

function auditTotpCode(string $secret): string
{
    return (new Google2FA())->getCurrentOtp($secret);
}

it('records the documented security events and exposes them via GET /users/me/audit', function (): void {
    $email = 'audit@example.com';
    $password = 'password123';

    $this->postJson('/auth/register', [
        'email' => $email,
        'password' => $password,
        'name' => 'Audit User',
    ])->assertCreated();

    $login = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    $token = (string) $login->json('token');
    $userId = (int) CorvantUserModel::query()->where('email', $email)->value('id');

    $secondLogin = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    $otherToken = (string) $secondLogin->json('token');

    $this->postJson('/auth/logout', [], [
        'Authorization' => 'Bearer '.$otherToken,
    ])->assertOk();

    $this->putJson('/users/me/password', [
        'current_password' => $password,
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $password = 'new-password-456';

    $tenant = CorvantTenantModel::query()->create([
        'name' => 'Audit Tenant',
        'slug' => 'audit-tenant',
    ]);
    $tenant->users()->attach($userId);

    $role = CorvantRoleModel::query()->create([
        'name' => 'auditor-role',
        'tenant_id' => $tenant->getKey(),
        'permissions' => ['audit:read'],
    ]);

    $this->postJson('/users/'.$userId.'/roles', [
        'role_id' => $role->getKey(),
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->getKey(),
    ])->assertOk();

    $enable = $this->postJson('/mfa/totp/enable', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $secret = (string) $enable->json('secret');

    $this->postJson('/mfa/totp/confirm', [
        'code' => auditTotpCode($secret),
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $mfaLogin = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->assertJson(['mfa_required' => true]);

    $challengeToken = (string) $mfaLogin->json('challenge_token');

    $this->postJson('/mfa/totp/verify', [
        'challenge_token' => $challengeToken,
        'code' => auditTotpCode($secret),
    ])->assertOk();

    $sessions = $this->getJson('/sessions', [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $otherSession = collect($sessions->json('data'))
        ->first(fn (array $row) => ! (bool) $row['is_current']);

    expect($otherSession)->not->toBeNull();

    $this->deleteJson('/sessions/'.$otherSession['id'], [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $audit = $this->getJson('/users/me/audit', [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $events = array_column($audit->json('data'), 'event');

    expect($events)->toContain(AuditEvents::LOGIN)
        ->and($events)->toContain(AuditEvents::LOGOUT)
        ->and($events)->toContain(AuditEvents::PASSWORD_CHANGED)
        ->and($events)->toContain(AuditEvents::ROLE_ASSIGNED)
        ->and($events)->toContain(AuditEvents::MFA_ENABLED);

    foreach ($audit->json('data') as $entry) {
        expect($entry)->not->toHaveKey('token');
        expect(json_encode($entry['metadata'] ?? []))->not->toContain('password');
    }

    $roleEntry = collect($audit->json('data'))->firstWhere('event', AuditEvents::ROLE_ASSIGNED);
    expect($roleEntry['tenant_id'])->toBe((int) $tenant->getKey())
        ->and($roleEntry['metadata']['role_name'])->toBe('auditor-role');

    expect($events)->toContain(AuditEvents::SESSION_REVOKED);
});

it('retains audit rows with a null user_id after the user is deleted', function (): void {
    $email = 'deleted-audit@example.com';
    $password = 'password123';

    $this->postJson('/auth/register', [
        'email' => $email,
        'password' => $password,
        'name' => 'Deleted User',
    ])->assertCreated();

    $login = $this->postJson('/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    $token = (string) $login->json('token');
    $userId = (int) CorvantUserModel::query()->where('email', $email)->value('id');

    expect(CorvantAuditLogModel::query()->where('user_id', $userId)->count())->toBeGreaterThan(0);

    $this->deleteJson('/users/me', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect(CorvantUserModel::query()->whereKey($userId)->exists())->toBeFalse();
    expect(CorvantAuditLogModel::query()->where('user_id', $userId)->count())->toBe(0);
    expect(CorvantAuditLogModel::query()->whereNull('user_id')->where('event', AuditEvents::LOGIN)->exists())->toBeTrue();
});
