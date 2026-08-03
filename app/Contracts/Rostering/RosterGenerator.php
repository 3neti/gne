<?php

namespace App\Contracts\Rostering;

use App\Domain\Rostering\GeneratedRosterResult;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationInput;

interface RosterGenerator
{
    public function generate(RosterGenerationInput $input, ResolvedRosterPolicy $policy): GeneratedRosterResult;
}
