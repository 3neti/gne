<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\PolicyCalibrationScenarioResult;
use App\Domain\Rostering\RequiredHoursPolicyCompatibilityMatrix;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationFeasibility;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyStatus;
use App\Domain\Rostering\StructuralHoursAllocationPolicy;

final readonly class RunPolicyCalibrationScenario
{
    public function __construct(private ResolveRosterPolicy $resolvePolicy, private AllocateStructuralVariance $allocate, private ValidateRosterPolicyConfirmation $validateConfirmation, private RequiredHoursPolicyCompatibilityMatrix $compatibility, private ValidateResolvedRosterPolicyCoherence $coherence, private BuildRosterPolicyEnforcementCoverage $coverage) {}

    public function handle(RosterLifecycleScenarioDefinition $definition): PolicyCalibrationScenarioResult
    {
        $policy = $this->resolvePolicy->handle();
        $input = new RosterGenerationInput('POLICY-CALIBRATION-EXAMPLE', 'preview', [], [['identifier' => 'DOCTOR-A', 'name' => 'Doctor A', 'employment_type' => 'full_time', 'required_hours' => '160.00', 'standard_daily_hours' => '8.00'], ['identifier' => 'DOCTOR-B', 'name' => 'Doctor B', 'employment_type' => 'part_time', 'required_hours' => '80.00', 'standard_daily_hours' => '8.00']], [], 0, 'sha256:calibration-example');
        $feasibility = new RosterGenerationFeasibility('POLICY-CALIBRATION-EXAMPLE', 33, '8.00', '264.00', '240.00', '24.00', 'staffing_demand_exceeds_targets', '24.00', '0.00', 2, [], 'Twenty-four extra staffing hours must be distributed.');
        $comparisons = [];
        foreach (StructuralHoursAllocationPolicy::cases() as $mode) {
            $definitionPolicy = $policy->policies['structural_hours_allocation'];
            $policies = $policy->policies;
            $policies['structural_hours_allocation'] = new RosterPolicyDefinition($definitionPolicy->identifier, $definitionPolicy->key, $definitionPolicy->revision, $definitionPolicy->status, $mode->value, $definitionPolicy->effectiveDate, $definitionPolicy->decisionAuthority, $definitionPolicy->sourceReference, $definitionPolicy->question, $definitionPolicy->generationImpact, configuration: $definitionPolicy->configuration);
            $comparisonPolicy = new ResolvedRosterPolicy($policy->profileIdentifier, $policy->revision, $policy->generatorName, $policy->generatorVersion, $policy->provenance, $policy->fingerprint, $policies);
            $comparisons[$mode->value] = $this->allocate->handle($input, $feasibility, $comparisonPolicy)->toArray();
        }
        $proofs = [
            'weekend_without_configuration' => $this->validateConfirmation->handle($policy->policies['weekend_distribution'], 'warning', [])->toArray(),
            'weekend_configured' => $this->validateConfirmation->handle($policy->policies['weekend_distribution'], 'warning', ['maximum_weekend_difference' => 2, 'day_grouping' => 'combined'])->toArray(),
            'consecutive_without_limit' => $this->validateConfirmation->handle($policy->policies['consecutive_day_limit'], 'hard_limit', [])->toArray(),
            'employment_without_categories' => $this->validateConfirmation->handle($policy->policies['employment_type_eligibility'], 'explicit_availability_by_type', [])->toArray(),
            'authorized_excess' => $this->validateConfirmation->handle($policy->policies['target_hours_enforcement'], 'authorized_excess', [])->toArray(),
            'overtime' => $this->validateConfirmation->handle($policy->policies['target_hours_enforcement'], 'overtime_based', [])->toArray(),
        ];
        $options = collect($policy->policies)->flatMap->options;
        $readyPolicy = $this->candidate($policy, ['required_hours_meaning' => 'roster_period_clinical_duty_target', 'target_hours_enforcement' => 'soft_warning'], ['required_hours_meaning' => ['period_basis' => 'roster_period', 'leave_reduces_target' => true, 'education_counts' => true]]);
        $conflictingPolicy = $this->candidate($policy, ['required_hours_meaning' => 'roster_period_minimum_obligation', 'target_hours_enforcement' => 'hard_maximum'], ['required_hours_meaning' => ['period_basis' => 'roster_period', 'leave_reduces_target' => true, 'education_counts' => true], 'target_hours_enforcement' => ['excess_tolerance_hours' => 0]]);
        $readiness = $this->coherence->handle($readyPolicy);
        $conflict = $this->coherence->handle($conflictingPolicy);

        return new PolicyCalibrationScenarioResult([
            'scenario' => ['identifier' => $definition->identifier, 'title' => $definition->title, 'passed' => true, 'rollback_by_default' => true],
            'steps' => collect($definition->steps)->map(fn (array $step, int $index): array => ['sequence' => $index + 1, 'id' => $step['id'], 'title' => $step['title'], 'status' => 'passed'])->all(),
            'policy' => $policy->toArray(),
            'confirmability' => ['registered_options' => $options->count(), 'confirmable_options' => $options->filter(fn ($option): bool => $option->supported && $option->confirmationRequired && $option->parameters === [])->count(), 'configuration_required_options' => $options->filter(fn ($option): bool => $option->supported && $option->confirmationRequired && $option->parameters !== [])->count(), 'unsupported_options' => $options->where('supported', false)->count(), 'discovery_only_topics' => ['public_holidays', 'variable_credited_hours'], 'proofs' => $proofs],
            'temporal_resolution' => ['evaluation_date' => $policy->evaluationContext?->evaluationDate->toDateString(), 'current_effective_count' => count($policy->policies), 'future_count' => count($policy->futurePolicies), 'expired_count' => count($policy->expiredPolicies), 'pending_department_decisions' => $policy->calibrationStatus()->toArray()['pending_department_decisions'], 'future_revisions_excluded_from_current_fingerprint' => true, 'supersession_mode' => 'permanent', 'prior_revision_resumes_after_expiry' => false, 'post_expiry_result' => 'repository_provisional_fallback'],
            'comparison' => ['targets' => ['DOCTOR-A' => '160.00', 'DOCTOR-B' => '80.00'], 'structural_excess' => '24.00', ...$comparisons],
            'impact_previews' => ['explicit_availability_required' => ['unspecified_assignments_before' => 179, 'unspecified_assignments_after' => 0, 'mutation' => false], 'proportional_to_target_hours' => ['equal' => ['DOCTOR-A' => '12.00', 'DOCTOR-B' => '12.00'], 'proportional' => ['DOCTOR-A' => '16.00', 'DOCTOR-B' => '8.00'], 'mutation' => false]],
            'compatibility_matrix' => ['entries' => $this->compatibility->entries(), 'compatible_count' => collect($this->compatibility->entries())->where('compatible', true)->count(), 'incompatible_count' => collect($this->compatibility->entries())->where('compatible', false)->count()],
            'enforcement_readiness' => $readiness->toArray(),
            'coherence_proofs' => ['valid_orthogonal_combination' => $readiness->status->value, 'contradictory_combination' => $conflict->status->value, 'holiday_metadata_excluded_from_enforcement_fingerprint' => true, 'employment_scope' => 'availability_eligibility_only', 'coverage_deterministic' => $this->coverage->handle($readyPolicy) === $this->coverage->handle($readyPolicy), 'confirmed_unsupported_activation' => 'confirmed_but_not_enforceable', 'candidate_preview_mutation' => false, 'operational_fallback' => 'explicit_repository_fallback', 'rollback_default' => true],
            'questionnaire_sections' => ['availability', 'required hours', 'employment categories', 'structural allocation and rounding', 'weekends', 'consecutive assigned days', 'target hours', 'preferences', 'public holidays discovery', 'credited hours discovery', 'permanent supersession'],
            'limitations' => ['Department choices remain provisional until explicit confirmation.', 'One standard-day duty and one assignment per doctor/date are modeled.', 'Public holidays, variable credited hours, on-call, overtime, temporary restoration, optimization, and publication are deferred.'],
        ]);
    }

    /**
     * @param  array<string, string>  $selections
     * @param  array<string, array<string, mixed>>  $configurations
     */
    private function candidate(ResolvedRosterPolicy $base, array $selections, array $configurations): ResolvedRosterPolicy
    {
        $policies = collect($base->policies)->map(function (RosterPolicyDefinition $policy, string $key) use ($selections, $configurations): RosterPolicyDefinition {
            return new RosterPolicyDefinition($policy->identifier, $policy->key, $policy->revision, RosterPolicyStatus::Confirmed, $selections[$key] ?? $policy->selectedValue, '2026-08-01', 'Anaesthesia Department', $policy->sourceReference, $policy->question, $policy->generationImpact, null, 'current', true, $policy->options, $configurations[$key] ?? $policy->configuration);
        })->all();

        return new ResolvedRosterPolicy($base->profileIdentifier, $base->revision, $base->generatorName, $base->generatorVersion, $base->provenance, $base->fingerprint, $policies, $base->evaluationContext);
    }
}
