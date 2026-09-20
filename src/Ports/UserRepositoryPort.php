<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\Email;

interface UserRepositoryPort
{
    public function findByEmail(Email $email): ?User;

    public function findById(int $id): ?User;

    public function save(User $user): User;

    public function existsByEmail(Email $email): bool;

    public function delete(int $id): void;
}
