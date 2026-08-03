<?php

namespace App\Domain\Rostering;

enum RosterDayType: string
{
    case Normal = 'normal';
    case Weekend = 'weekend';
    case PublicHoliday = 'public_holiday';
}
