<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Entities;

use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;

final class User
{
    public function __construct(
        private ?int $id,
        private Email $email,
        private HashedPassword $password,
        private string $name,
    ) {}

    public static function register(
        Email $email,
        HashedPassword $password,
        string $name,
    ): self {
        return new self(null, $email, $password, $name);
    }

    public function withId(int $id): self
    {
        return new self($id, $this->email, $this->password, $this->name);
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
}
