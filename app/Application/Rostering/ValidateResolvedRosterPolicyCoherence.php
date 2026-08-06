<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\RequiredHoursPolicyCompatibilityMatrix;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterPolicyCoherenceFinding;
use App\Domain\Rostering\RosterPolicyEnforcementReadiness;
use App\Domain\Rostering\RosterPolicyEnforcementReadinessStatus;
use App\Domain\Rostering\RosterPolicyOption;
use App\Domain\Rostering\RosterPolicyStatus;

final readonly class ValidateResolvedRosterPolicyCoherence
{
    public function __construct(private RequiredHoursPolicyCompatibilityMatrix $compatibility, private BuildRosterPolicyEnforcementCoverage $coverage, private EvaluateRosterPolicySetAgainstPeriod $periodEvaluator) {}

    public function handle(ResolvedRosterPolicy $policy, ?RosterGenerationInput $input = null): RosterPolicyEnforcementReadiness
    {
        $findings = [];
        $meaning = $policy->policies['required_hours_meaning'];
        $enforcement = $policy->policies['target_hours_enforcement'];
        if (! in_array($meaning->selectedValue, ['unresolved'], true) && ! in_array($enforcement->selectedValue, ['unresolved', 'authorized_excess', 'overtime_based'], true) && ! $this->compatibility->compatible($meaning->selectedValue, $enforcement->selectedValue)) {
            $findings[] = new RosterPolicyCoherenceFinding('error', 'POLICY_REQUIRED_HOURS_CONFLICT', 'Required-hours meaning conflicts with target-hours enforcement.', ['required_hours_meaning', 'target_hours_enforcement'], ['required_hours_meaning' => $meaning->selectedValue, 'target_hours_enforcement' => $enforcement->selectedValue], $input?->periodIdentifier, [], [], 'Select an enforcement mode compatible with the authored hours meaning.');
        }
        $availability = $policy->policies['unspecified_availability'];
        $employment = $policy->policies['employment_type_eligibility'];
        if ($availability->selectedValue === 'explicit_availability_required' && $employment->selectedValue === 'all_active_types_eligible') {
            $findings[] = new RosterPolicyCoherenceFinding('error', 'POLICY_EMPLOYMENT_SCOPE_INCOMPLETE', 'Global explicit availability conflicts with an employment rule declaring all active types eligible without that exception.', ['unspecified_availability', 'employment_type_eligibility'], ['unspecified_availability' => $availability->selectedValue, 'employment_type_eligibility' => $employment->selectedValue], $input?->periodIdentifier, [], [], 'Use an explicit-by-category employment rule or eligible-unless-blocked globally.');
        } elseif ($availability->selectedValue === 'explicit_availability_required' && $employment->selectedValue === 'explicit_availability_by_type') {
            $findings[] = new RosterPolicyCoherenceFinding('warning', 'POLICY_EMPLOYMENT_SCOPE_REDUNDANT', 'Employment-category explicit availability is redundant under the global explicit rule.', ['unspecified_availability', 'employment_type_eligibility'], ['unspecified_availability' => $availability->selectedValue, 'employment_type_eligibility' => $employment->selectedValue], $input?->periodIdentifier, [], [], 'Keep the redundancy only when it is an intentional recorded decision.');
        }
        foreach ($policy->policies as $key => $definition) {
            $option = null;
            foreach ($definition->options as $candidate) {
                if ($candidate->value === $definition->selectedValue) {
                    $option = $candidate;

                    break;
                }
            }
            $missingParameter = false;
            if ($option instanceof RosterPolicyOption) {
                foreach ($option->parameters as $parameter) {
                    $missingParameter = $missingParameter || ($parameter->required && ! array_key_exists($parameter->key, $definition->configuration));
                }
            }
            if ($definition->selectedValue === 'unresolved' || $missingParameter) {
                $findings[] = new RosterPolicyCoherenceFinding('error', 'POLICY_CONFIGURATION_INCOMPLETE', "Policy {$key} is not operationally complete.", [$key], [$key => $definition->selectedValue], $input?->periodIdentifier, [], [], 'Select a resolved option and supply every required operational parameter.');
            }
            if ($option instanceof RosterPolicyOption && ! $option->supported) {
                $findings[] = new RosterPolicyCoherenceFinding('error', 'POLICY_UNSUPPORTED_DEPENDENCY', "Policy {$key} depends on deferred runtime behavior.", [$key], [$key => $definition->selectedValue], $input?->periodIdentifier, [], [], 'Retain this as a recorded decision or select a currently supported option.');
            }
        }
        $coverage = $this->coverage->handle($policy);
        foreach (array_filter($coverage, fn (array $entry): bool => ! $entry['enforcement_ready']) as $entry) {
            $findings[] = new RosterPolicyCoherenceFinding('error', 'POLICY_GENERATOR_SUPPORT_MISSING', "Selected {$entry['policy_key']} behavior is not implemented consistently by runtime consumers.", [$entry['policy_key']], [$entry['policy_key'] => $entry['selected_value']], $input?->periodIdentifier, [], [], 'Implement matching generator and validator support before activation.');
        }
        $period = $input === null ? null : $this->periodEvaluator->handle($policy, $input);
        if ($period !== null) {
            $findings = [...$findings, ...$period->findings];
        }
        usort($findings, fn (RosterPolicyCoherenceFinding $left, RosterPolicyCoherenceFinding $right): int => [$left->severity, $left->code, implode('|', $left->policyKeys)] <=> [$right->severity, $right->code, implode('|', $right->policyKeys)]);
        $status = match (true) {
            collect($findings)->contains(fn (RosterPolicyCoherenceFinding $finding): bool => $finding->code === 'POLICY_REQUIRED_HOURS_CONFLICT' || $finding->code === 'POLICY_EMPLOYMENT_SCOPE_INCOMPLETE') => RosterPolicyEnforcementReadinessStatus::PolicyConflict,
            $period !== null && ! $period->isFeasible() => RosterPolicyEnforcementReadinessStatus::PeriodInfeasible,
            collect($findings)->contains(fn (RosterPolicyCoherenceFinding $finding): bool => in_array($finding->code, ['POLICY_UNSUPPORTED_DEPENDENCY', 'POLICY_GENERATOR_SUPPORT_MISSING', 'POLICY_VALIDATOR_SUPPORT_MISSING'], true)) => RosterPolicyEnforcementReadinessStatus::UnsupportedDependency,
            collect($findings)->contains(fn (RosterPolicyCoherenceFinding $finding): bool => $finding->code === 'POLICY_CONFIGURATION_INCOMPLETE') => RosterPolicyEnforcementReadinessStatus::ConfigurationIncomplete,
            default => RosterPolicyEnforcementReadinessStatus::ReadyForEnforcement,
        };
        $confirmed = collect($policy->policies)->filter(fn ($definition): bool => $definition->status === RosterPolicyStatus::Confirmed)->count();
        $provisional = collect($policy->policies)->filter(fn ($definition): bool => $definition->status === RosterPolicyStatus::Provisional)->count();
        $activation = $status === RosterPolicyEnforcementReadinessStatus::ReadyForEnforcement ? 'confirmed_and_enforceable' : ($confirmed > 0 ? 'confirmed_but_not_enforceable' : 'provisional_fallback');

        return new RosterPolicyEnforcementReadiness($status, $policy->evaluationContext?->evaluationDate->toDateString() ?? now()->toDateString(), $input?->periodIdentifier, $policy->fingerprint, $policy->enforcementFingerprint(), $confirmed, $provisional, $findings, $coverage, $period, $activation);
    }
}
