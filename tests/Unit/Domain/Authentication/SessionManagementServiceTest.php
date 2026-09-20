<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Exceptions\SessionNotFoundException;
use Corvant\Domain\Authentication\Services\SessionManagementService;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Domain\Audit\AuditEvents;
use Corvant\Ports\AuditLoggerPort;
use Corvant\Ports\SessionStorePort;

function sessionForUser(int $userId, string $token): Session
{
    return new Session($token, $userId, new DateTimeImmutable('+1 hour'));
}

it('derives a stable public session id from the token', function (): void {
    $session = new Session('secret-token', 1, new DateTimeImmutable('+1 hour'));

    expect($session->id())->toBe(hash('sha256', 'secret-token'));
});

it('lists sessions only for the requested user via the port', function (): void {
    $userId = 5;
    $sessions = [
        sessionForUser($userId, 'token-a'),
        sessionForUser($userId, 'token-b'),
    ];

    $store = Mockery::mock(SessionStorePort::class);
    $store->shouldReceive('allForUser')->once()->with($userId)->andReturn($sessions);

    $service = new SessionManagementService($store, Mockery::mock(AuditLoggerPort::class));
    $result = $service->listSessions($userId);

    expect($result)->toHaveCount(2);
});

it('revokes a session when the id matches one of the user sessions', function (): void {
    $userId = 3;
    $target = sessionForUser($userId, 'revoke-me');
    $other = sessionForUser($userId, 'keep-me');

    $store = Mockery::mock(SessionStorePort::class);
    $store->shouldReceive('allForUser')->once()->with($userId)->andReturn([$target, $other]);
    $store->shouldReceive('revoke')->once()->with('revoke-me');

    $audit = Mockery::mock(AuditLoggerPort::class);
    $audit->shouldReceive('log')->once()->with(AuditEvents::SESSION_REVOKED, $userId, null, [
        'session_id' => $target->id(),
    ]);

    $service = new SessionManagementService($store, $audit);
    $service->revokeSession($userId, $target->id(), 'keep-me');
});

it('rejects revoking a session id that belongs to another user', function (): void {
    $userId = 1;
    $foreignId = hash('sha256', 'foreign-token');

    $store = Mockery::mock(SessionStorePort::class);
    $store->shouldReceive('allForUser')->once()->with($userId)->andReturn([
        sessionForUser($userId, 'own-token'),
    ]);
    $store->shouldReceive('revoke')->never();

    $service = new SessionManagementService($store, Mockery::mock(AuditLoggerPort::class));
    $service->revokeSession($userId, $foreignId, 'own-token');
})->throws(SessionNotFoundException::class);

it('rejects revoking a nonexistent session id with the same not-found outcome', function (): void {
    $userId = 1;

    $store = Mockery::mock(SessionStorePort::class);
    $store->shouldReceive('allForUser')->once()->with($userId)->andReturn([
        sessionForUser($userId, 'own-token'),
    ]);
    $store->shouldReceive('revoke')->never();

    $service = new SessionManagementService($store, Mockery::mock(AuditLoggerPort::class));
    $service->revokeSession($userId, 'does-not-exist', 'own-token');
})->throws(SessionNotFoundException::class);

it('never revokes the current session when revoking other sessions', function (): void {
    $userId = 7;
    $current = sessionForUser($userId, 'current-token');
    $other = sessionForUser($userId, 'other-token');

    $store = Mockery::mock(SessionStorePort::class);
    $store->shouldReceive('allForUser')->once()->with($userId)->andReturn([$current, $other]);
    $store->shouldReceive('revoke')->once()->with('other-token');

    $audit = Mockery::mock(AuditLoggerPort::class);
    $audit->shouldReceive('log')->once()->with(AuditEvents::SESSION_REVOKED, $userId, null, [
        'session_id' => $other->id(),
    ]);

    $service = new SessionManagementService($store, $audit);
    $service->revokeOtherSessions($userId, 'current-token');
});
