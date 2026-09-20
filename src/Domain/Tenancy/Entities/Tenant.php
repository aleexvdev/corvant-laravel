<?php

declare(strict_types=1);

namespace Corvant\Domain\Tenancy\Entities;

use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;

final class Tenant
{
    public function __construct(
        private ?int $id,
        private string $name,
        private TenantSlug $slug,
    ) {}

    public static function create(string $name, TenantSlug $slug): self
    {
        return new self(null, $name, $slug);
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name, $this->slug);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): TenantSlug
    {
        return $this->slug;
    }
}
