<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\EmailAlreadyExistsException;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Ports\NotificationPort;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\SingleUseTokenPort;
use Corvant\Ports\UserRepositoryPort;

function makeProfileAuthenticationService(
    UserRepositoryPort $users,
    SessionStorePort $sessions,
    PasswordHasherPort $hasher,
    SingleUseTokenPort $tokens,
    NotificationPort $notifications,
): AuthenticationService {
    return new AuthenticationService($users, $sessions, $hasher, $tokens, $notifications, 3600, 86400, 86400);
}

it('updates only profile fields via updateProfile', function (): void {
    $user = new User(1, new Email('user@example.com'), new HashedPassword('hash'), 'Old Name');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('save')->once()->andReturnUsing(function (User $saved) {
        expect($saved->name())->toBe('New Name')
            ->and($saved->avatarUrl())->toBe('https://example.com/a.png')
            ->and($saved->locale())->toBe('en')
            ->and($saved->timezone())->toBe('UTC');

        return $saved;
    });

    $service = makeProfileAuthenticationService(
        $users,
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(SingleUseTokenPort::class),
        Mockery::mock(NotificationPort::class),
    );

    $service->updateProfile($user, 'New Name', 'https://example.com/a.png', 'en', 'UTC');
});

it('rejects email change when the new email is already taken', function (): void {
    $user = new User(1, new Email('user@example.com'), new HashedPassword('hash'), 'User');
    $newEmail = new Email('taken@example.com');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('existsByEmail')->once()->with($newEmail)->andReturn(true);

    $service = makeProfileAuthenticationService(
        $users,
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(SingleUseTokenPort::class),
        Mockery::mock(NotificationPort::class),
    );

    $service->requestEmailChange($user, $newEmail);
})->throws(EmailAlreadyExistsException::class);

it('throws when confirming email change with an invalid token', function (): void {
    $user = new User(1, new Email('user@example.com'), new HashedPassword('hash'), 'User', null, null, null, null, null, new Email('new@example.com'));

    $tokens = Mockery::mock(SingleUseTokenPort::class);
    $tokens->shouldReceive('consume')->once()->andReturn(null);

    $service = makeProfileAuthenticationService(
        Mockery::mock(UserRepositoryPort::class),
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        $tokens,
        Mockery::mock(NotificationPort::class),
    );

    $service->confirmEmailChange($user, 'bad-token');
})->throws(InvalidOrExpiredTokenException::class);

it('rejects password change when the current password is wrong', function (): void {
    $user = new User(1, new Email('user@example.com'), new HashedPassword('hash'), 'User');

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->once()->with('wrong', 'hash')->andReturn(false);

    $service = makeProfileAuthenticationService(
        Mockery::mock(UserRepositoryPort::class),
        Mockery::mock(SessionStorePort::class),
        $hasher,
        Mockery::mock(SingleUseTokenPort::class),
        Mockery::mock(NotificationPort::class),
    );

    $service->changePassword($user, 'wrong', 'new-password');
})->throws(InvalidCredentialsException::class);

it('revokes the session when deleting an account', function (): void {
    $user = new User(7, new Email('delete@example.com'), new HashedPassword('hash'), 'User');

    $sessions = Mockery::mock(SessionStorePort::class);
    $sessions->shouldReceive('revoke')->once()->with('session-token');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('delete')->once()->with(7);

    $service = makeProfileAuthenticationService(
        $users,
        $sessions,
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(SingleUseTokenPort::class),
        Mockery::mock(NotificationPort::class),
    );

    $service->deleteAccount($user, 'session-token');
});
