<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterGenerator;
use App\Domain\Rostering\GeneratedRosterResult;
use App\Domain\Rostering\InvalidRosterGeneration;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Domain\Rostering\RosterPolicyEvaluationContext;
use App\Domain\Rostering\RosterPolicyEvaluationPurpose;
use App\Models\RosterPeriod;

final readonly class PreviewDraftRosterGeneration
{
    public function __construct(private BuildRosterGenerationInput $input, private SelectOperationalRosterPolicy $selectPolicy, private RosterGenerator $generator, private AnalyzeGeneratedRoster $analyze) {}

    public function handle(RosterPeriod $period): GeneratedRosterResult
    {
        if ($period->status !== RosterPeriodStatus::ReadyForGeneration) {
            throw new InvalidRosterGeneration('Initial draft generation requires ready_for_generation status.');
        }
        $input = $this->input->handle($period);
        if ($input->existingAssignmentCount > 0) {
            throw new InvalidRosterGeneration('Initial draft generation cannot overwrite existing assignments.');
        }
        $selection = $this->selectPolicy->handle(new RosterPolicyEvaluationContext(
            evaluationDate: $period->start_date->toImmutable(),
            purpose: RosterPolicyEvaluationPurpose::GenerationPreview,
            rosterPeriodIdentifier: $period->identifier,
        ), $input);
        $policy = $selection->operationalPolicy;
        if ($policy->calibrationStatus()->blocksGeneration()) {
            throw new InvalidRosterGeneration('The explicit operational fallback is incomplete: '.implode(', ', $policy->calibrationStatus()->unresolvedMandatory).'.');
        }

        return $this->analyze->handle($input, $this->generator->generate($input, $policy), $policy);
    }
}
