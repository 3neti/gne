<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterGenerator;
use App\Domain\Rostering\GeneratedRosterResult;
use App\Domain\Rostering\InvalidRosterGeneration;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\RosterPeriod;

final readonly class PreviewDraftRosterGeneration
{
    public function __construct(private BuildRosterGenerationInput $input, private ResolveRosterPolicy $policy, private RosterGenerator $generator, private AnalyzeGeneratedRoster $analyze) {}

    public function handle(RosterPeriod $period): GeneratedRosterResult
    {
        if ($period->status !== RosterPeriodStatus::ReadyForGeneration) {
            throw new InvalidRosterGeneration('Initial draft generation requires ready_for_generation status.');
        }
        $input = $this->input->handle($period);
        if ($input->existingAssignmentCount > 0) {
            throw new InvalidRosterGeneration('Initial draft generation cannot overwrite existing assignments.');
        }
        $policy = $this->policy->handle();
        if ($policy->calibrationStatus()->blocksGeneration()) {
            throw new InvalidRosterGeneration('Generation is blocked until mandatory department policies are calibrated: '.implode(', ', $policy->calibrationStatus()->unresolvedMandatory).'.');
        }

        return $this->analyze->handle($input, $this->generator->generate($input, $policy), $policy);
    }
}
