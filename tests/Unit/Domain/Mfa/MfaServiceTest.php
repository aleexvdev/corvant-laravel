<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Domain\Authentication\ValueObjects\MfaChallenge;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Domain\Mfa\Exceptions\MfaAlreadyEnabledException;
use Corvant\Domain\Mfa\Services\MfaService;
use Corvant\Ports\MfaChallengePort;
use Corvant\Ports\MfaProviderPort;
use Corvant\Ports\MfaRecoveryCodePort;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\UserRepositoryPort;

function makeMfaService(
    UserRepositoryPort $users,
    MfaProviderPort $mfaProvider,
    MfaChallengePort $challenges,
    SessionStorePort $sessions,
    PasswordHasherPort $hasher,
    MfaRecoveryCodePort $recoveryCodes,
    int $recoveryCodesCount = 10,
): MfaService {
    return new MfaService(
        $users,
        $mfaProvider,
        $challenges,
        $sessions,
        $hasher,
        $recoveryCodes,
        $recoveryCodesCount,
    );
}

function baseUser(?string $totpSecret = null, ?string $pendingTotpSecret = null): User
{
    return new User(
        1,
        new Email('user@example.com'),
        new HashedPassword('password-hash'),
        'User',
        null,
        null,
        null,
        null,
        null,
        null,
        $totpSecret,
        $pendingTotpSecret,
    );
}

it('enables TOTP by storing a pending secret and returning otpauth metadata', function (): void {
    $user = baseUser();

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('save')->once()->andReturnUsing(fn (User $u) => $u);

    $mfaProvider = Mockery::mock(MfaProviderPort::class);
    $mfaProvider->shouldReceive('generateSecret')->once()->andReturn('SECRET123');
    $mfaProvider->shouldReceive('otpauthUri')->once()->with('SECRET123', 'user@example.com')->andReturn('otpauth://totp/Corvant:user@example.com?secret=SECRET123');

    $service = makeMfaService(
        $users,
        $mfaProvider,
        Mockery::mock(MfaChallengePort::class),
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(MfaRecoveryCodePort::class),
    );

    $result = $service->enableTotp($user);

    expect($result['secret'])->toBe('SECRET123')
        ->and($result['otpauth_uri'])->toContain('otpauth://');
});

it('throws when enabling MFA while it is already active', function (): void {
    $user = baseUser('active-secret');

    $service = makeMfaService(
        Mockery::mock(UserRepositoryPort::class),
        Mockery::mock(MfaProviderPort::class),
        Mockery::mock(MfaChallengePort::class),
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(MfaRecoveryCodePort::class),
    );

    $service->enableTotp($user);
})->throws(MfaAlreadyEnabledException::class);

it('confirms MFA when the pending secret matches the TOTP code', function (): void {
    $user = baseUser(null, 'pending-secret');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('save')->once()->andReturnUsing(fn (User $u) => $u);

    $mfaProvider = Mockery::mock(MfaProviderPort::class);
    $mfaProvider->shouldReceive('verifyCode')->once()->with('pending-secret', '123456')->andReturn(true);

    $service = makeMfaService(
        $users,
        $mfaProvider,
        Mockery::mock(MfaChallengePort::class),
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(MfaRecoveryCodePort::class),
    );

    $service->confirmTotp($user, '123456');
});

it('rejects MFA confirm when the TOTP code is wrong', function (): void {
    $user = baseUser(null, 'pending-secret');

    $mfaProvider = Mockery::mock(MfaProviderPort::class);
    $mfaProvider->shouldReceive('verifyCode')->once()->andReturn(false);

    $service = makeMfaService(
        Mockery::mock(UserRepositoryPort::class),
        $mfaProvider,
        Mockery::mock(MfaChallengePort::class),
        Mockery::mock(SessionStorePort::class),
        Mockery::mock(PasswordHasherPort::class),
        Mockery::mock(MfaRecoveryCodePort::class),
    );

    $service->confirmTotp($user, '123456');
})->throws(InvalidCredentialsException::class);

it('rejects disable MFA when the password is wrong', function (): void {
    $user = baseUser('active-secret');

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->once()->with('wrong', 'password-hash')->andReturn(false);

    $service = makeMfaService(
        Mockery::mock(UserRepositoryPort::class),
        Mockery::mock(MfaProviderPort::class),
        Mockery::mock(MfaChallengePort::class),
        Mockery::mock(SessionStorePort::class),
        $hasher,
        Mockery::mock(MfaRecoveryCodePort::class),
    );

    $service->disableMfa($user, 'wrong');
})->throws(InvalidCredentialsException::class);

it('generates the configured number of recovery codes', function (): void {
    $user = baseUser('active-secret');

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('hash')->times(3)->andReturn('hash');

    $recoveryCodes = Mockery::mock(MfaRecoveryCodePort::class);
    $recoveryCodes->shouldReceive('replaceAllForUser')->once()->with(1, ['hash', 'hash', 'hash']);

    $service = makeMfaService(
        Mockery::mock(UserRepositoryPort::class),
        Mockery::mock(MfaProviderPort::class),
        Mockery::mock(MfaChallengePort::class),
        Mockery::mock(SessionStorePort::class),
        $hasher,
        $recoveryCodes,
        3,
    );

    $codes = $service->generateRecoveryCodes($user);

    expect($codes)->toHaveCount(3);
});

it('marks a recovery code as used after a successful login challenge completion', function (): void {
    $user = baseUser('active-secret');
    $challenge = new MfaChallenge('challenge-1', 1, new DateTimeImmutable('+5 minutes'));
    $session = new Session('session-token', 1, new DateTimeImmutable('+1 hour'));

    $challenges = Mockery::mock(MfaChallengePort::class);
    $challenges->shouldReceive('find')->once()->with('challenge-1')->andReturn($challenge);
    $challenges->shouldReceive('revoke')->once()->with('challenge-1');

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findById')->once()->with(1)->andReturn($user);

    $recoveryCodes = Mockery::mock(MfaRecoveryCodePort::class);
    $recoveryCodes->shouldReceive('unusedForUser')->once()->with(1)->andReturn([
        new \Corvant\Domain\Mfa\Entities\StoredRecoveryCode(5, 'stored-hash'),
    ]);
    $recoveryCodes->shouldReceive('markUsed')->once()->with(5);

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->once()->with('RC-1', 'stored-hash')->andReturn(true);

    $sessions = Mockery::mock(SessionStorePort::class);
    $sessions->shouldReceive('create')->once()->with($user)->andReturn($session);

    $service = makeMfaService(
        $users,
        Mockery::mock(MfaProviderPort::class),
        $challenges,
        $sessions,
        $hasher,
        $recoveryCodes,
    );

    $result = $service->useMfaRecoveryCode('challenge-1', 'RC-1');

    expect($result->token())->toBe('session-token');
});

it('fails when reusing the same recovery code', function (): void {
    $user = baseUser('active-secret');
    $challenge = new MfaChallenge('challenge-1', 1, new DateTimeImmutable('+5 minutes'));

    $challenges = Mockery::mock(MfaChallengePort::class);
    $challenges->shouldReceive('find')->once()->andReturn($challenge);
    $challenges->shouldReceive('revoke')->never();

    $users = Mockery::mock(UserRepositoryPort::class);
    $users->shouldReceive('findById')->once()->andReturn($user);

    $recoveryCodes = Mockery::mock(MfaRecoveryCodePort::class);
    $recoveryCodes->shouldReceive('unusedForUser')->once()->andReturn([]);

    $hasher = Mockery::mock(PasswordHasherPort::class);
    $hasher->shouldReceive('verify')->never();

    $service = makeMfaService(
        $users,
        Mockery::mock(MfaProviderPort::class),
        $challenges,
        Mockery::mock(SessionStorePort::class),
        $hasher,
        $recoveryCodes,
    );

    $service->useMfaRecoveryCode('challenge-1', 'RC-1');
})->throws(InvalidCredentialsException::class);
