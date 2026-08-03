<?php

namespace App\Domain\Rostering;

enum DoctorRequestType: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Leave = 'leave';
    case PreferredWork = 'preferred_work';
    case PreferredOff = 'preferred_off';

    public function isBlocking(): bool
    {
        return in_array($this, [self::Leave, self::Unavailable], true);
    }
}
