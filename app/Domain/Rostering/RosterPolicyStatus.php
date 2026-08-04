<?php

namespace App\Domain\Rostering;

enum RosterPolicyStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Provisional = 'provisional';
    case Confirmed = 'confirmed';
    case Superseded = 'superseded';
    case Rejected = 'rejected';
}
