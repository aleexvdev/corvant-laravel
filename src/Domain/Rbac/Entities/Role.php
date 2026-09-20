<?php

declare(strict_types=1);

namespace Corvant\Domain\Rbac\Entities;

final class Role
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        private ?int $id,
        private string $name,
        private ?int $tenantId,
        private array $permissions,
    ) {}

    /**
     * @param list<string> $permissions
     */
    public static function create(string $name, ?int $tenantId, array $permissions): self
    {
        return new self(null, $name, $tenantId, $permissions);
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name, $this->tenantId, $this->permissions);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function isGlobalSuperAdmin(): bool
    {
        return $this->tenantId === null && strcasecmp($this->name, 'SuperAdmin') === 0;
    }
}
