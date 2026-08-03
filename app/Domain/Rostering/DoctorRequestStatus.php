<?php

namespace App\Domain\Rostering;

enum DoctorRequestStatus: string
{
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function isEffective(): bool
    {
        return $this === self::Accepted;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Accepted, self::Rejected, self::Withdrawn],
            self::Accepted => [self::Withdrawn],
            default => [],
        };
    }
}
