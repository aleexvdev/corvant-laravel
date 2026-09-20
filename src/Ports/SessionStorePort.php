<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\Session;

interface SessionStorePort
{
    public function create(User $user): Session;

    public function find(string $token): ?Session;

    public function revoke(string $token): void;

    /**
     * @return list<Session>
     */
    public function allForUser(int $userId): array;
}
