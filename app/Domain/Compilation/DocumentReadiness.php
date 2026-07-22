<?php

namespace App\Domain\Compilation;

enum DocumentReadiness: string
{
    case Resolved = 'resolved';
    case Pending = 'pending';
    case Unavailable = 'unavailable';
    case NotApplicable = 'not_applicable';
}
