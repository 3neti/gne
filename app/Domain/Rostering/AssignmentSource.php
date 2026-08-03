<?php

namespace App\Domain\Rostering;

enum AssignmentSource: string
{
    case Manual = 'manual';
    case Generated = 'generated';
    case Imported = 'imported';
}
