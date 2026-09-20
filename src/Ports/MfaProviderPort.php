<?php

declare(strict_types=1);

namespace Corvant\Ports;

interface MfaProviderPort
{
    public function generateSecret(): string;

    public function otpauthUri(string $secret, string $accountLabel): string;

    public function verifyCode(string $secret, string $code): bool;
}
