<?php

namespace App\Domain\Rostering;

enum AssignmentSource: string
{
    case ManuallyAdded = 'manually_added';
    case ManuallyChanged = 'manually_changed';
    case Manual = 'manual';
    case Generated = 'generated';
    case Imported = 'imported';
}
