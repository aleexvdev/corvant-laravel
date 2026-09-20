<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Entities;

use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use DateTimeImmutable;
use LogicException;

final class User
{
    public function __construct(
        private ?int $id,
        private Email $email,
        private HashedPassword $password,
        private string $name,
        private ?DateTimeImmutable $emailVerifiedAt = null,
        private ?string $avatarUrl = null,
        private ?string $locale = null,
        private ?string $timezone = null,
        private ?string $phone = null,
        private ?Email $pendingEmail = null,
        private ?string $totpSecret = null,
        private ?string $pendingTotpSecret = null,
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
        return $this->copy(id: $id);
    }

    public function withPassword(HashedPassword $password): self
    {
        return $this->copy(password: $password);
    }

    public function verifyEmail(): self
    {
        return $this->copy(emailVerifiedAt: new DateTimeImmutable());
    }

    public function withProfile(
        string $name,
        ?string $avatarUrl,
        ?string $locale,
        ?string $timezone,
    ): self {
        return $this->copy(
            name: $name,
            avatarUrl: $avatarUrl,
            locale: $locale,
            timezone: $timezone,
        );
    }

    public function withPhone(?string $phone): self
    {
        return $this->copy(phone: $phone);
    }

    public function withPendingEmail(?Email $pendingEmail): self
    {
        return $this->copy(pendingEmail: $pendingEmail);
    }

    public function withPendingTotpSecret(?string $pendingTotpSecret): self
    {
        return $this->copy(pendingTotpSecret: $pendingTotpSecret);
    }

    public function withConfirmedTotpSecret(string $totpSecret): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->password,
            $this->name,
            $this->emailVerifiedAt,
            $this->avatarUrl,
            $this->locale,
            $this->timezone,
            $this->phone,
            $this->pendingEmail,
            $totpSecret,
            null,
        );
    }

    public function withMfaDisabled(): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->password,
            $this->name,
            $this->emailVerifiedAt,
            $this->avatarUrl,
            $this->locale,
            $this->timezone,
            $this->phone,
            $this->pendingEmail,
            null,
            null,
        );
    }

    public function withConfirmedEmailChange(): self
    {
        if ($this->pendingEmail === null) {
            throw new LogicException('Cannot confirm email change without a pending email.');
        }

        return new self(
            $this->id,
            $this->pendingEmail,
            $this->password,
            $this->name,
            new DateTimeImmutable(),
            $this->avatarUrl,
            $this->locale,
            $this->timezone,
            $this->phone,
            null,
            $this->totpSecret,
            $this->pendingTotpSecret,
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

    public function avatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function pendingEmail(): ?Email
    {
        return $this->pendingEmail;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function totpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function pendingTotpSecret(): ?string
    {
        return $this->pendingTotpSecret;
    }

    public function hasMfaEnabled(): bool
    {
        return $this->totpSecret !== null;
    }

    private function copy(
        ?int $id = null,
        ?Email $email = null,
        ?HashedPassword $password = null,
        ?string $name = null,
        ?DateTimeImmutable $emailVerifiedAt = null,
        ?string $avatarUrl = null,
        ?string $locale = null,
        ?string $timezone = null,
        ?string $phone = null,
        ?Email $pendingEmail = null,
        ?string $totpSecret = null,
        ?string $pendingTotpSecret = null,
    ): self {
        return new self(
            $id ?? $this->id,
            $email ?? $this->email,
            $password ?? $this->password,
            $name ?? $this->name,
            $emailVerifiedAt ?? $this->emailVerifiedAt,
            $avatarUrl ?? $this->avatarUrl,
            $locale ?? $this->locale,
            $timezone ?? $this->timezone,
            $phone ?? $this->phone,
            $pendingEmail ?? $this->pendingEmail,
            $totpSecret ?? $this->totpSecret,
            $pendingTotpSecret ?? $this->pendingTotpSecret,
        );
    }
}
