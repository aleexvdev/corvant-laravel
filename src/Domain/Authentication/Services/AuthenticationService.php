<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Services;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\EmailAlreadyExistsException;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Ports\NotificationPort;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\SingleUseTokenPort;
use Corvant\Ports\UserRepositoryPort;
use DateTimeImmutable;

final class AuthenticationService
{
    private const PURPOSE_PASSWORD_RESET = 'password-reset';

    private const PURPOSE_EMAIL_VERIFICATION = 'email-verification';

    public function __construct(
        private UserRepositoryPort $users,
        private SessionStorePort $sessions,
        private PasswordHasherPort $hasher,
        private SingleUseTokenPort $singleUseTokens,
        private NotificationPort $notifications,
        private int $passwordResetTtlSeconds,
        private int $emailVerificationTtlSeconds,
    ) {}

    public function register(Email $email, string $plainPassword, string $name): User
    {
        if ($this->users->existsByEmail($email)) {
            throw new EmailAlreadyExistsException($email);
        }

        $hashed = new HashedPassword($this->hasher->hash($plainPassword));
        $user = User::register($email, $hashed, $name);

        return $this->users->save($user);
    }

    public function login(Email $email, string $plainPassword): Session
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! $this->hasher->verify($plainPassword, $user->password()->hash())) {
            throw new InvalidCredentialsException();
        }

        return $this->sessions->create($user);
    }

    public function logout(string $sessionToken): void
    {
        $this->sessions->revoke($sessionToken);
    }

    public function refresh(string $currentToken): Session
    {
        $session = $this->sessions->find($currentToken);
        if ($session === null || $session->expiresAt() < new DateTimeImmutable()) {
            throw new InvalidOrExpiredTokenException();
        }

        $user = $this->users->findById($session->userId());
        if ($user === null) {
            throw new InvalidOrExpiredTokenException();
        }

        $newSession = $this->sessions->create($user);
        $this->sessions->revoke($currentToken);

        return $newSession;
    }

    public function requestPasswordReset(Email $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }

        $token = $this->singleUseTokens->issue(
            $email->value(),
            self::PURPOSE_PASSWORD_RESET,
            $this->passwordResetTtlSeconds,
        );

        $this->notifications->sendPasswordResetLink($email, $token);
    }

    public function resetPassword(string $token, string $newPlainPassword): void
    {
        $emailValue = $this->singleUseTokens->consume($token, self::PURPOSE_PASSWORD_RESET);
        if ($emailValue === null) {
            throw new InvalidOrExpiredTokenException();
        }

        $user = $this->users->findByEmail(new Email($emailValue));
        if ($user === null) {
            throw new InvalidOrExpiredTokenException();
        }

        $hashed = new HashedPassword($this->hasher->hash($newPlainPassword));
        $this->users->save($user->withPassword($hashed));
    }

    public function requestEmailVerification(User $user): void
    {
        if ($user->isEmailVerified()) {
            return;
        }

        $token = $this->singleUseTokens->issue(
            $user->email()->value(),
            self::PURPOSE_EMAIL_VERIFICATION,
            $this->emailVerificationTtlSeconds,
        );

        $this->notifications->sendEmailVerificationLink($user->email(), $token);
    }

    public function verifyEmail(string $token): void
    {
        $emailValue = $this->singleUseTokens->consume($token, self::PURPOSE_EMAIL_VERIFICATION);
        if ($emailValue === null) {
            throw new InvalidOrExpiredTokenException();
        }

        $user = $this->users->findByEmail(new Email($emailValue));
        if ($user === null || $user->isEmailVerified()) {
            throw new InvalidOrExpiredTokenException();
        }

        $this->users->save($user->verifyEmail());
    }
}
