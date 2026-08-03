<?php

namespace App\Domain\Rostering;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Visiting = 'visiting';
    case Locum = 'locum';
}
