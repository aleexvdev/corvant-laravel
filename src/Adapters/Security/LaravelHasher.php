<?php

declare(strict_types=1);

namespace Corvant\Adapters\Security;

use Corvant\Ports\PasswordHasherPort;
use Illuminate\Support\Facades\Hash;

final class LaravelHasher implements PasswordHasherPort
{
    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public function verify(string $plain, string $hashed): bool
    {
        return Hash::check($plain, $hashed);
    }
}
