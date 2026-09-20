<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Mfa\Entities\StoredRecoveryCode;

interface MfaRecoveryCodePort
{
    /**
     * @param  list<string>  $hashedCodes
     */
    public function replaceAllForUser(int $userId, array $hashedCodes): void;

    /**
     * @return list<StoredRecoveryCode>
     */
    public function unusedForUser(int $userId): array;

    public function markUsed(int $id): void;

    public function invalidateAllUnusedForUser(int $userId): void;
}
