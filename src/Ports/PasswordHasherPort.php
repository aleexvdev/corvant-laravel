<?php

declare(strict_types=1);

namespace Corvant\Ports;

interface PasswordHasherPort
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hashed): bool;
}
