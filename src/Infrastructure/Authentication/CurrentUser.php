<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Authentication;

use Corvant\Domain\Authentication\Entities\User;

final class CurrentUser
{
    private ?User $user = null;

    public function set(?User $user): void
    {
        $this->user = $user;
    }

    public function get(): ?User
    {
        return $this->user;
    }
}
