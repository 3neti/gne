<?php

namespace App\Domain\Rostering;

use DomainException;

final class OverlappingEffectiveDoctorRequest extends DomainException
{
    /**
     * @param  list<string>  $overlappingDates
     * @param  list<string>  $requestIdentifiers
     */
    public function __construct(public readonly array $overlappingDates, public readonly array $requestIdentifiers)
    {
        parent::__construct('An accepted request of this type already covers one or more selected dates: '.implode(', ', $overlappingDates).'. Existing requests: '.implode(', ', $requestIdentifiers).'.');
    }
}
