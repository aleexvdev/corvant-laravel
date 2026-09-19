<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Exceptions;

use Corvant\Domain\Authentication\ValueObjects\Email;
use DomainException;

final class EmailAlreadyExistsException extends DomainException
{
    public function __construct(Email $email)
    {
        parent::__construct(sprintf('A user with email %s already exists.', $email->value()));
    }
}
