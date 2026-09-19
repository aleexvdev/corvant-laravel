<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Domain\Authentication\ValueObjects\HashedPassword;
use Corvant\Ports\UserRepositoryPort;

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

    public function save(User $user): User
    {
        if ($user->id() !== null) {
            $model = CorvantUserModel::query()->findOrFail($user->id());
            $model->fill([
                'email' => $user->email()->value(),
                'password' => $user->password()->hash(),
                'name' => $user->name(),
            ]);
            $model->save();

            return $this->toDomain($model);
        }

        $model = CorvantUserModel::query()->create([
            'email' => $user->email()->value(),
            'password' => $user->password()->hash(),
            'name' => $user->name(),
        ]);

        return $this->toDomain($model);
    }

    public function existsByEmail(Email $email): bool
    {
        return CorvantUserModel::query()
            ->where('email', $email->value())
            ->exists();
    }

    private function toDomain(CorvantUserModel $model): User
    {
        return new User(
            (int) $model->getKey(),
            new Email($model->email),
            new HashedPassword($model->password),
            $model->name,
        );
    }
}
