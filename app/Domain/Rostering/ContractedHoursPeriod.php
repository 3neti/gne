<?php

namespace App\Domain\Rostering;

enum ContractedHoursPeriod: string
{
    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';
    case FourWeek = 'four_week';
    case RosterPeriod = 'roster_period';
    case Other = 'other';
}
