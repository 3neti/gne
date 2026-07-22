<?php

namespace App\Domain\Repository;

enum ValidationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
