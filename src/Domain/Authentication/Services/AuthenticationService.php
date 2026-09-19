<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Services;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\EmailAlreadyExistsException;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\UserRepositoryPort;

final class AuthenticationService
{
    public function __construct(
        private UserRepositoryPort $users,
        private SessionStorePort $sessions,
        private PasswordHasherPort $hasher,
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
}
