<?php

namespace App\Domain\Rostering;

enum StructuralHoursAllocationPolicy: string
{
    case EqualPerEligibleDoctor = 'equal_per_eligible_doctor';
    case ProportionalToTargetHours = 'proportional_to_target_hours';
    case Unresolved = 'unresolved';
}
