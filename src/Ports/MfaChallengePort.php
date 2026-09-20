<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\MfaChallenge;

interface MfaChallengePort
{
    public function create(User $user): MfaChallenge;

    public function find(string $token): ?MfaChallenge;

    public function revoke(string $token): void;
}
