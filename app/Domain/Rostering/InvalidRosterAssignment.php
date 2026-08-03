<?php

namespace App\Domain\Rostering;

use DomainException;

final class InvalidRosterAssignment extends DomainException
{
    /** @param list<RosterValidationFinding> $findings */
    public function __construct(public readonly array $findings, string $message = 'The roster assignment violates a mandatory rule.')
    {
        parent::__construct($message);
    }
}
