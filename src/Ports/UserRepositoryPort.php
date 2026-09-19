<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\Email;

interface UserRepositoryPort
{
    public function findByEmail(Email $email): ?User;

    public function save(User $user): User;

    public function existsByEmail(Email $email): bool;
}
