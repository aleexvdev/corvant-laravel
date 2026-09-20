<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Domain\Authentication\ValueObjects\Session;
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
): AuthenticationService {
    return new AuthenticationService($users, $sessions, $hasher, $singleUseTokens, $notifications, 3600, 86400);
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
