<?php

namespace App\Domain\Rostering;

enum RosterPolicyConfirmability: string
{
    case Confirmable = 'confirmable';
    case ConfigurationRequired = 'configuration_required';
    case UnsupportedInCurrentRelease = 'unsupported_in_current_release';
    case Unresolved = 'unresolved';
    case Invalid = 'invalid';
}
