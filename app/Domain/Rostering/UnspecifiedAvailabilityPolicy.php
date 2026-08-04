<?php

namespace App\Domain\Rostering;

enum UnspecifiedAvailabilityPolicy: string
{
    case EligibleUnlessBlocked = 'eligible_unless_blocked';
    case ExplicitAvailabilityRequired = 'explicit_availability_required';
    case Unresolved = 'unresolved';
}
