<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\EmailAlreadyExistsException;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Domain\Authentication\Exceptions\MfaChallengeRequiredException;
use Corvant\Ports\MfaChallengePort;
use Corvant\Ports\NotificationPort;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\SingleUseTokenPort;
use Corvant\Ports\UserRepositoryPort;

function makeAuthenticationService(
    UserRepositoryPort $users,
    SessionStorePort $sessions,
    PasswordHasherPort $hasher,
    ?SingleUseTokenPort $singleUseTokens = null,
    ?NotificationPort $notifications = null,
    ?MfaChallengePort $mfaChallenges = null,
): AuthenticationService {
    return new AuthenticationService(
        $users,
        $sessions,
        $mfaChallenges ?? Mockery::mock(MfaChallengePort::class),
        $hasher,
        $singleUseTokens ?? Mockery::mock(SingleUseTokenPort::class),
        $notifications ?? Mockery::mock(NotificationPort::class),
        3600,
        86400,
        86400,
    );
}

it('registers a new user when email is available', function (): void {
    $email = new Email('new@example.com');
    $hashed = new HashedPassword('hashed-secret');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('existsByEmail')->once()->with($email)->andReturn(false);
    $users->shouldReceive('save')->once()->andReturnUsing(function (User $user) {
        return $user->withId(1);
    });

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('hash')->once()->with('plain-pass')->andReturn('hashed-secret');

    $sessions = Mockery::mock(SessionStorePort::class);

    $service = makeAuthenticationService($users, $sessions, $hasher);
    $user = $service->register($email, 'plain-pass', 'Ada');

    expect($user->id())->toBe(1)
        ->and($user->email()->value())->toBe('new@example.com')
        ->and($user->name())->toBe('Ada');
});

it('throws when registering a duplicate email', function (): void {
    $email = new Email('taken@example.com');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('existsByEmail')->once()->with($email)->andReturn(true);

    $service = makeAuthenticationService(
        $users,
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
    );

    $service->register($email, 'secret', 'Name');
})->throws(EmailAlreadyExistsException::class);

it('logs in and returns a session for valid credentials', function (): void {
    $email = new Email('user@example.com');
    $stored = new User(
        10,
        $email,
        new HashedPassword('stored-hash'),
        'User',
    );
    $session = new Session('token-abc', 10, new DateTimeImmutable('+1 hour'));

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findByEmail')->once()->with($email)->andReturn($stored);

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->once()->with('correct', 'stored-hash')->andReturn(true);

    $sessions = Mockery::mock(SessionStorePort::class);
    $sessions->shouldReceive('create')->once()->with($stored)->andReturn($session);

    $service = makeAuthenticationService($users, $sessions, $hasher);
    $result = $service->login($email, 'correct');

    expect($result->token())->toBe('token-abc');
});

it('throws MfaChallengeRequiredException when MFA is enabled instead of creating a session', function (): void {
    $email = new Email('mfa@example.com');
    $stored = new User(
        10,
        $email,
        new HashedPassword('stored-hash'),
        'User',
        null,
        null,
        null,
        null,
        null,
        null,
        'totp-secret-value',
        null,
    );

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findByEmail')->once()->with($email)->andReturn($stored);

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->once()->with('correct', 'stored-hash')->andReturn(true);

    $sessions = Mockery::mock(SessionStorePort::class);
    $sessions->shouldReceive('create')->never();

    $mfaChallenges = Mockery::mock(MfaChallengePort::class);
    $mfaChallenges->shouldReceive('create')->once()->with($stored)->andReturn(
        new \Corvant\Domain\Authentication\ValueObjects\MfaChallenge('challenge-token', 10, new DateTimeImmutable('+5 minutes')),
    );

    $service = makeAuthenticationService($users, $sessions, $hasher, mfaChallenges: $mfaChallenges);

    try {
        $service->login($email, 'correct');
        expect(false)->toBeTrue('Expected MfaChallengeRequiredException');
    } catch (MfaChallengeRequiredException $e) {
        expect($e->challengeToken())->toBe('challenge-token');
    }
});

it('rejects login with wrong password', function (): void {
    $email = new Email('user@example.com');
    $stored = new User(
        10,
        $email,
        new HashedPassword('stored-hash'),
        'User',
    );

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($stored);

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->once()->andReturn(false);

    $service = makeAuthenticationService(
        $users,
        Mockery::mock(SessionStorePort::class),
        $hasher,
    );

    $service->login($email, 'wrong');
})->throws(InvalidCredentialsException::class);

it('rejects login when user is not found', function (): void {
    $email = new Email('missing@example.com');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findByEmail')->once()->andReturn(null);

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->never();

    $service = makeAuthenticationService(
        $users,
        Mockery::mock(SessionStorePort::class),
        $hasher,
    );

    $service->login($email, 'secret');
})->throws(InvalidCredentialsException::class);

it('revokes the session on logout', function (): void {
    $sessions = Mockery::mock(SessionStorePort::class);
    $sessions->shouldReceive('revoke')->once()->with('session-token');

    $service = makeAuthenticationService(
        Mockery::mock(UserRepositoryPort::class),
        $sessions,
        Mockery::mock(PasswordHasherPort::class),
    );

    $service->logout('session-token');
});
