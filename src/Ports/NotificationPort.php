<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Authentication\ValueObjects\Email;

interface NotificationPort
{
    public function sendPasswordResetLink(Email $to, string $token): void;

    public function sendEmailVerificationLink(Email $to, string $token): void;
}
