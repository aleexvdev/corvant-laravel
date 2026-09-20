<?php

declare(strict_types=1);

namespace Corvant\Ports;

use Corvant\Domain\Audit\Entities\AuditLogEntry;

interface AuditLoggerPort
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $event, ?int $userId, ?int $tenantId, array $metadata = []): void;

    /**
     * @return list<AuditLogEntry>
     */
    public function forUser(int $userId, int $limit = 50): array;
}
