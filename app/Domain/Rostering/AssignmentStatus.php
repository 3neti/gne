<?php

namespace App\Domain\Rostering;

enum AssignmentStatus: string
{
    case Assigned = 'assigned';
    case Leave = 'leave';
    case Unavailable = 'unavailable';
}
