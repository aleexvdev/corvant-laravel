<?php

declare(strict_types=1);

namespace Corvant\Ports;

interface LoginAttemptPort
{
    public function isLocked(string $email): bool;

    public function recordFailure(string $email): void;

    public function clear(string $email): void;
}
