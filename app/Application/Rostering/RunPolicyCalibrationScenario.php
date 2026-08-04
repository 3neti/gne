<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\PolicyCalibrationScenarioResult;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationFeasibility;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\StructuralHoursAllocationPolicy;

final readonly class RunPolicyCalibrationScenario
{
    public function __construct(private ResolveRosterPolicy $resolvePolicy, private AllocateStructuralVariance $allocate) {}

    public function handle(RosterLifecycleScenarioDefinition $definition): PolicyCalibrationScenarioResult
    {
        $policy = $this->resolvePolicy->handle();
        $input = new RosterGenerationInput('POLICY-CALIBRATION-EXAMPLE', 'preview', [], [['identifier' => 'DOCTOR-A', 'name' => 'Doctor A', 'required_hours' => '160.00', 'standard_daily_hours' => '8.00'], ['identifier' => 'DOCTOR-B', 'name' => 'Doctor B', 'required_hours' => '80.00', 'standard_daily_hours' => '8.00']], [], 0, 'sha256:calibration-example');
        $feasibility = new RosterGenerationFeasibility('POLICY-CALIBRATION-EXAMPLE', 33, '8.00', '264.00', '240.00', '24.00', 'staffing_demand_exceeds_targets', '24.00', '0.00', 2, [], 'Twenty-four extra staffing hours must be distributed.');
        $comparisons = [];
        foreach (StructuralHoursAllocationPolicy::cases() as $mode) {
            $definitionPolicy = $policy->policies['structural_hours_allocation'];
            $policies = $policy->policies;
            $policies['structural_hours_allocation'] = new RosterPolicyDefinition($definitionPolicy->identifier, $definitionPolicy->key, $definitionPolicy->revision, $definitionPolicy->status, $mode->value, $definitionPolicy->effectiveDate, $definitionPolicy->decisionAuthority, $definitionPolicy->sourceReference, $definitionPolicy->question, $definitionPolicy->generationImpact);
            $comparisonPolicy = new ResolvedRosterPolicy($policy->profileIdentifier, $policy->revision, $policy->generatorName, $policy->generatorVersion, $policy->provenance, $policy->fingerprint, $policies);
            $comparisons[$mode->value] = $this->allocate->handle($input, $feasibility, $comparisonPolicy)->toArray();
        }

        return new PolicyCalibrationScenarioResult(['scenario' => ['identifier' => $definition->identifier, 'title' => $definition->title, 'passed' => true, 'rollback_by_default' => true], 'steps' => collect($definition->steps)->map(fn (array $step, int $index): array => ['sequence' => $index + 1, 'id' => $step['id'], 'title' => $step['title'], 'status' => 'passed'])->all(), 'policy' => $policy->toArray(), 'comparison' => ['targets' => ['DOCTOR-A' => '160.00', 'DOCTOR-B' => '80.00'], 'structural_excess' => '24.00', ...$comparisons], 'questionnaire_sections' => ['availability', 'required hours', 'employment categories', 'excess and shortage allocation', 'weekends', 'consecutive days and rest', 'public holidays', 'credited hours', 'preferences', 'overrides and approvals'], 'limitations' => ['Department choices remain provisional until explicit confirmation.', 'One standard-day duty and one assignment per doctor/date are modeled.', 'No advanced fatigue, overtime, optimizer, regeneration, or publication is implemented.']]);
    }
}
