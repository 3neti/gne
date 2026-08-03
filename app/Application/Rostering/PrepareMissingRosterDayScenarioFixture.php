<?php

namespace App\Application\Rostering;

use App\Models\RosterPeriod;

/**
 * Creates the one controlled invalid state used to prove the readiness error gate.
 * This is scenario infrastructure and is never exposed through an HTTP boundary.
 */
final class PrepareMissingRosterDayScenarioFixture
{
    public function handle(RosterPeriod $period): void
    {
        $period->days()->orderBy('date')->firstOrFail()->delete();
    }
}
