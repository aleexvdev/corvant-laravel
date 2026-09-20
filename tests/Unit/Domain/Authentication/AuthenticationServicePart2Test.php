<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Domain\Audit\AuditEvents;
use Corvant\Ports\AuditLoggerPort;
use Corvant\Ports\LoginAttemptPort;
use Corvant\Ports\NotificationPort;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\SingleUseTokenPort;
use Corvant\Ports\UserRepositoryPort;

function makeAuthenticationServicePart2(
    UserRepositoryPort $users,
    SessionStorePort $sessions,
    PasswordHasherPort $hasher,
    SingleUseTokenPort $singleUseTokens,
    NotificationPort $notifications,
    ?AuditLoggerPort $auditLogger = null,
): AuthenticationService {
    return new AuthenticationService(
        $users,
        $sessions,
        Mockery::mock(\Corvant\Ports\MfaChallengePort::class),
        $hasher,
        $singleUseTokens,
        $notifications,
        $auditLogger ?? Mockery::mock(AuditLoggerPort::class),
        Mockery::mock(LoginAttemptPort::class),
        3600,
        86400,
        86400,
    );
}

it('rotates the session token on refresh and revokes the old one', function (): void {
    $user = new User(5, new Email('user@example.com'), new HashedPassword('hash'), 'User');
    $oldSession = new Session('old-token', 5, new DateTimeImmutable('+1 hour'));
    $newSession = new Session('new-token', 5, new DateTimeImmutable('+2 hours'));

    $sessions = Mockery::mock(SessionStorePort::class);
    $sessions->shouldReceive('find')->once()->with('old-token')->andReturn($oldSession);
    $sessions->shouldReceive('create')->once()->with($user)->andReturn($newSession);
    $sessions->shouldReceive('revoke')->once()->with('old-token');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findById')->once()->with(5)->andReturn($user);

    $service = makeAuthenticationServicePart2(
        $users,
        $sessions,
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(SingleUseTokenPort::class),
        Mockery::mock(NotificationPort::class),
    );

    expect($service->refresh('old-token')->token())->toBe('new-token');
});

it('does not issue a password reset token when the email is unknown', function (): void {
    $email = new Email('missing@example.com');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findByEmail')->once()->with($email)->andReturn(null);

    $tokens = Mockery::mock(SingleUseTokenPort::class);
    $tokens->shouldReceive('issue')->never();

    $notifications = Mockery::mock(NotificationPort::class);
    $notifications->shouldReceive('sendPasswordResetLink')->never();

    $service = makeAuthenticationServicePart2(
        $users,
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        $tokens,
        $notifications,
    );

    $service->requestPasswordReset($email);
});

it('throws when resetting password with an invalid token', function (): void {
    $tokens = Mockery::mock(SingleUseTokenPort::class);
    $tokens->shouldReceive('consume')->once()->with('bad-token', 'password-reset')->andReturn(null);

    $service = makeAuthenticationServicePart2(
        Mockery::mock(UserRepositoryPort::class),
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        $tokens,
        Mockery::mock(NotificationPort::class),
    );

    $service->resetPassword('bad-token', 'new-password');
})->throws(InvalidOrExpiredTokenException::class);

it('marks the user verified when consuming a valid verification token', function (): void {
    $user = new User(1, new Email('verify@example.com'), new HashedPassword('hash'), 'User');

    $tokens = Mockery::mock(SingleUseTokenPort::class);
    $tokens->shouldReceive('consume')->once()->with('verify-token', 'email-verification')->andReturn('verify@example.com');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);
    $users->shouldReceive('save')->once()->andReturnUsing(function (User $saved) {
        expect($saved->isEmailVerified())->toBeTrue();

        return $saved;
    });

    $service = makeAuthenticationServicePart2(
        $users,
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        $tokens,
        Mockery::mock(NotificationPort::class),
    );

    $service->verifyEmail('verify-token');
});

it('logs password changed when resetting password with a valid token', function (): void {
    $user = new User(3, new Email('reset@example.com'), new HashedPassword('old-hash'), 'User');

    $tokens = Mockery::mock(SingleUseTokenPort::class);
    $tokens->shouldReceive('consume')->once()->with('reset-token', 'password-reset')->andReturn('reset@example.com');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);
    $users->shouldReceive('save')->once()->andReturnUsing(fn (User $saved): User => $saved);

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('hash')->once()->with('new-password')->andReturn('new-hash');

    $audit = Mockery::mock(AuditLoggerPort::class);
    $audit->shouldReceive('log')->once()->with(AuditEvents::PASSWORD_CHANGED, 3, null);

    $service = makeAuthenticationServicePart2(
        $users,
        Mockery::mock(SessionStorePort::class),
        $hasher,
        $tokens,
        Mockery::mock(NotificationPort::class),
        $audit,
    );

    $service->resetPassword('reset-token', 'new-password');
});
