<?php

declare(strict_types=1);

namespace Corvant\Domain\Audit\Entities;

use DateTimeImmutable;

final class AuditLogEntry
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private int $id,
        private string $event,
        private ?int $userId,
        private ?int $tenantId,
        private array $metadata,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function event(): string
    {
        return $this->event;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
