<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyImpactPreview;

final readonly class PreviewRosterPolicyImpact
{
    public function __construct(private ResolveRosterPolicy $resolvePolicy) {}

    public function handle(string $policyKey, string $candidateValue): RosterPolicyImpactPreview
    {
        $current = $this->resolvePolicy->handle();
        $definition = $current->policies[$policyKey] ?? throw new \InvalidArgumentException('Unknown policy key.');
        if (! in_array($candidateValue, RosterPolicyDefinition::allowedValues($policyKey), true)) {
            throw new \InvalidArgumentException('Unknown policy option.');
        }
        $candidatePolicies = collect($current->policies)->map->toArray()->all();
        $candidatePolicies[$policyKey]['selected_value'] = $candidateValue;
        $candidateContent = json_encode(collect($candidatePolicies)->sortKeys()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $option = collect($definition->options)->firstWhere('value', $candidateValue);
        [$generation, $validation, $quality, $doctors, $dates] = match ([$policyKey, $candidateValue]) {
            ['unspecified_availability', 'explicit_availability_required'] => ['Only explicitly available doctor/date cells remain eligible; the demonstration would remove 179 of 180 unspecified-eligibility assignments.', 'Assignments without explicit accepted availability become errors.', 'Readiness may fall and understaffable dates must be reviewed.', [], []],
            ['structural_hours_allocation', 'proportional_to_target_hours'] => ['Structural excess is allocated 16 hours to the 160-hour target and 8 hours to the 80-hour target.', 'Residual variance is checked after the 16/8 allocation.', 'Allocation is classified proportionally instead of the equal 12/12 working assumption.', ['DOCTOR-A', 'DOCTOR-B'], []],
            default => [$option?->impact ?? $definition->generationImpact, 'Validation would apply the registered candidate from the evaluation date.', 'Quality classification would use the candidate without changing stored policy or roster state.', [], []],
        };

        return new RosterPolicyImpactPreview($policyKey, $definition->selectedValue, $candidateValue, $current->evaluationContext?->evaluationDate->toDateString() ?? '', $doctors, $dates, $generation, $validation, $quality, $current->fingerprint, 'sha256:'.hash('sha256', $candidateContent), [], ['Preview uses current demonstration facts and does not mutate policy or roster state.']);
    }
}
