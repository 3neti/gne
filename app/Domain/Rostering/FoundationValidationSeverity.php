<?php

namespace App\Domain\Rostering;

enum FoundationValidationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
