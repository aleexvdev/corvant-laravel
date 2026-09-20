<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Corvant\Domain\Mfa\Entities\StoredRecoveryCode;
use Corvant\Ports\MfaRecoveryCodePort;
final class EloquentMfaRecoveryCodeRepository implements MfaRecoveryCodePort
{
    public function replaceAllForUser(int $userId, array $hashedCodes): void
    {
        CorvantMfaRecoveryCodeModel::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->delete();

        $now = now();

        foreach ($hashedCodes as $hash) {
            CorvantMfaRecoveryCodeModel::query()->create([
                'user_id' => $userId,
                'code_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function unusedForUser(int $userId): array
    {
        return CorvantMfaRecoveryCodeModel::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CorvantMfaRecoveryCodeModel $row) => new StoredRecoveryCode(
                (int) $row->getKey(),
                $row->code_hash,
            ))
            ->all();
    }

    public function markUsed(int $id): void
    {
        CorvantMfaRecoveryCodeModel::query()
            ->whereKey($id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    public function invalidateAllUnusedForUser(int $userId): void
    {
        CorvantMfaRecoveryCodeModel::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }
}
