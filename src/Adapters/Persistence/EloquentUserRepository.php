<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Ports\UserRepositoryPort;
use DateTimeImmutable;

/**
 * Persists domain users in Corvant's corvant_users table (package-owned schema).
 * A separate adapter may later implement UserRepositoryPort against the consumer's
 * Authenticatable model without changing domain code.
 */
final class EloquentUserRepository implements UserRepositoryPort
{
    public function findByEmail(Email $email): ?User
    {
        $model = CorvantUserModel::query()
            ->where('email', $email->value())
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findById(int $id): ?User
    {
        $model = CorvantUserModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function save(User $user): User
    {
        $attributes = [
            'email' => $user->email()->value(),
            'password' => $user->password()->hash(),
            'name' => $user->name(),
            'email_verified_at' => $user->emailVerifiedAt(),
            'avatar_url' => $user->avatarUrl(),
            'locale' => $user->locale(),
            'timezone' => $user->timezone(),
            'phone' => $user->phone(),
            'pending_email' => $user->pendingEmail()?->value(),
            'totp_secret' => $user->totpSecret(),
            'pending_totp_secret' => $user->pendingTotpSecret(),
        ];

        if ($user->id() !== null) {
            $model = CorvantUserModel::query()->findOrFail($user->id());
            $model->fill($attributes);
            $model->save();

            return $this->toDomain($model);
        }

        $model = CorvantUserModel::query()->create($attributes);

        return $this->toDomain($model);
    }

    public function existsByEmail(Email $email): bool
    {
        $value = $email->value();

        return CorvantUserModel::query()
            ->where('email', $value)
            ->orWhere('pending_email', $value)
            ->exists();
    }

    public function delete(int $id): void
    {
        CorvantUserModel::query()->whereKey($id)->delete();
    }

    private function toDomain(CorvantUserModel $model): User
    {
        $verifiedAt = $model->email_verified_at !== null
            ? new DateTimeImmutable((string) $model->email_verified_at)
            : null;

        $pendingEmail = $model->pending_email !== null && $model->pending_email !== ''
            ? new Email($model->pending_email)
            : null;

        return new User(
            (int) $model->getKey(),
            new Email($model->email),
            new HashedPassword($model->password),
            $model->name,
            $verifiedAt,
            $model->avatar_url,
            $model->locale,
            $model->timezone,
            $model->phone,
            $pendingEmail,
            $model->totp_secret,
            $model->pending_totp_secret,
        );
    }
}
