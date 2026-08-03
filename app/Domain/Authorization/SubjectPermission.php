<?php

namespace App\Domain\Authorization;

enum SubjectPermission: string
{
    case View = 'view';
}
