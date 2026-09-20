<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Corvant\Domain\Audit\Entities\AuditLogEntry;
use Corvant\Ports\AuditLoggerPort;
use DateTimeImmutable;

final class EloquentAuditLogger implements AuditLoggerPort
{
    public function log(string $event, ?int $userId, ?int $tenantId, array $metadata = []): void
    {
        CorvantAuditLogModel::query()->create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        return CorvantAuditLogModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (CorvantAuditLogModel $row): AuditLogEntry {
                $metadata = $row->metadata;
                if (! is_array($metadata)) {
                    $metadata = [];
                }

                return new AuditLogEntry(
                    (int) $row->getKey(),
                    $row->event,
                    $row->user_id !== null ? (int) $row->user_id : null,
                    $row->tenant_id !== null ? (int) $row->tenant_id : null,
                    $metadata,
                    new DateTimeImmutable((string) $row->created_at),
                );
            })
            ->all();
    }
}
