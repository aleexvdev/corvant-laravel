<?php

declare(strict_types=1);

use Corvant\Domain\Tenancy\Exceptions\InvalidTenantSlugException;
use Corvant\Domain\Tenancy\ValueObjects\TenantSlug;

it('normalizes slug to lowercase', function (): void {
    $slug = new TenantSlug('Acme-Corp');

    expect($slug->value())->toBe('acme-corp');
});

it('accepts alphanumeric segments separated by hyphens', function (): void {
    $slug = new TenantSlug('team-42');

    expect($slug->value())->toBe('team-42');
});

it('rejects invalid slug characters', function (): void {
    new TenantSlug('bad_slug!');
})->throws(InvalidTenantSlugException::class);

it('rejects empty slug', function (): void {
    new TenantSlug('   ');
})->throws(InvalidTenantSlugException::class);
