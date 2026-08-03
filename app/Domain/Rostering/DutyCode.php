<?php

namespace App\Domain\Rostering;

enum DutyCode: string
{
    case StandardDay = 'standard_day';
    case Leave = 'leave';
    case Unavailable = 'unavailable';
}
