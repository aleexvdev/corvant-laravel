<?php

declare(strict_types=1);

namespace Corvant\Ports;

interface SingleUseTokenPort
{
    public function issue(string $subject, string $purpose, int $ttlSeconds): string;

    public function consume(string $token, string $purpose): ?string;
}
