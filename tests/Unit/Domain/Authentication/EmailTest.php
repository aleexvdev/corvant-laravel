<?php

declare(strict_types=1);

use Corvant\Domain\Authentication\Exceptions\InvalidEmailException;
use Corvant\Domain\Authentication\ValueObjects\Email;

it('normalizes email to lowercase', function (): void {
    $email = new Email('User@Example.COM');

    expect($email->value())->toBe('user@example.com');
});

it('trims whitespace around email', function (): void {
    $email = new Email('  user@example.com  ');

    expect($email->value())->toBe('user@example.com');
});

it('rejects invalid email format', function (): void {
    new Email('not-an-email');
})->throws(InvalidEmailException::class);

it('reports equality case-insensitively after normalization', function (): void {
    $a = new Email('a@example.com');
    $b = new Email('A@EXAMPLE.COM');

    expect($a->equals($b))->toBeTrue();
});
