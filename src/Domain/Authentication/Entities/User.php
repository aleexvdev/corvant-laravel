<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Entities;

use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use DateTimeImmutable;

final class User
{
    public function __construct(
        private ?int $id,
        private Email $email,
        private HashedPassword $password,
        private string $name,
        private ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    public static function register(
        Email $email,
        HashedPassword $password,
        string $name,
    ): self {
        return new self(null, $email, $password, $name, null);
    }

    public function withId(int $id): self
    {
        return new self($id, $this->email, $this->password, $this->name, $this->emailVerifiedAt);
    }

    public function withPassword(HashedPassword $password): self
    {
        return new self($this->id, $this->email, $password, $this->name, $this->emailVerifiedAt);
    }

    public function verifyEmail(): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->password,
            $this->name,
            new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}
