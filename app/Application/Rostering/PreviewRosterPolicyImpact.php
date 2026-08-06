<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyImpactPreview;

final readonly class PreviewRosterPolicyImpact
{
    public function __construct(private ResolveRosterPolicy $resolvePolicy, private ValidateRosterPolicyConfirmation $validateConfirmation) {}

    /** @param array<string, mixed> $configuration */
    public function handle(string $policyKey, string $candidateValue, array $configuration = []): RosterPolicyImpactPreview
    {
        $current = $this->resolvePolicy->handle();
        $definition = $current->policies[$policyKey] ?? throw new \InvalidArgumentException('Unknown policy key.');
        if (! in_array($candidateValue, RosterPolicyDefinition::allowedValues($policyKey), true)) {
            throw new \InvalidArgumentException('Unknown policy option.');
        }
        $confirmationValidation = $this->validateConfirmation->handle($definition, $candidateValue, $configuration);
        $candidatePolicies = collect($current->policies)->map->toArray()->all();
        $candidatePolicies[$policyKey]['selected_value'] = $candidateValue;
        $candidatePolicies[$policyKey]['configuration'] = $confirmationValidation->configuration;
        $candidateContent = json_encode(collect($candidatePolicies)->sortKeys()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $option = collect($definition->options)->firstWhere('value', $candidateValue);
        [$generation, $validationImpact, $quality, $doctors, $dates] = match ([$policyKey, $candidateValue]) {
            ['unspecified_availability', 'explicit_availability_required'] => ['Only explicitly available doctor/date cells remain eligible; the demonstration would remove 179 of 180 unspecified-eligibility assignments.', 'Assignments without explicit accepted availability become errors.', 'Readiness may fall and understaffable dates must be reviewed.', [], []],
            ['structural_hours_allocation', 'proportional_to_target_hours'] => ['Structural excess is allocated 16 hours to the 160-hour target and 8 hours to the 80-hour target.', 'Residual variance is checked after the 16/8 allocation.', 'Allocation is classified proportionally instead of the equal 12/12 working assumption.', ['DOCTOR-A', 'DOCTOR-B'], []],
            ['weekend_distribution', 'warning'] => ['A maximum weekend difference of '.($confirmationValidation->configuration['maximum_weekend_difference'] ?? 'an unresolved threshold').' would be evaluated using '.($confirmationValidation->configuration['day_grouping'] ?? 'an unresolved grouping').'.', 'The current demonstration range is 2; a configured threshold below 2 introduces a warning.', 'Weekend balance becomes classifiable only after both parameters are supplied.', [], []],
            ['consecutive_day_limit', 'hard_limit'] => ['The current maximum assigned run is 7 days; a limit of '.($confirmationValidation->configuration['maximum_consecutive_days'] ?? 'an unresolved value').' would be enforced.', 'A configured limit below 7 introduces one or more hard violations.', 'Leave and unassigned dates break the run; on-call and rest-after-run are not modeled.', [], []],
            ['employment_type_eligibility', 'explicit_availability_by_type'] => ['Explicit availability would be required for '.implode(', ', $confirmationValidation->configuration['explicit_availability_required_for'] ?? []).'.', 'Assignments for affected categories without accepted availability become errors.', 'Affected doctors, eligible cells, and dates depend on the selected roster period.', [], []],
            default => [$option?->impact ?? $definition->generationImpact, 'Validation would apply the registered candidate from the evaluation date.', 'Quality classification would use the candidate without changing stored policy or roster state.', [], []],
        };

        $warnings = $confirmationValidation->isConfirmable() ? [] : ['Candidate is '.$confirmationValidation->confirmability->value.' and cannot be confirmed yet.'];

        return new RosterPolicyImpactPreview($policyKey, $definition->selectedValue, $candidateValue, $confirmationValidation->configuration, $confirmationValidation->confirmability->value, $current->evaluationContext?->evaluationDate->toDateString() ?? '', $doctors, $dates, $generation, $validationImpact, $quality, $current->fingerprint, 'sha256:'.hash('sha256', $candidateContent), $warnings, ['Preview uses current demonstration facts and does not mutate policy or roster state.']);
    }
}
