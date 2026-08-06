<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\ResolvedRosterPolicy;

final readonly class BuildRosterPolicyEnforcementCoverage
{
    /** @return list<array<string, mixed>> */
    public function handle(ResolvedRosterPolicy $policy): array
    {
        $entries = [];
        foreach ($policy->policies as $key => $definition) {
            [$generator, $validator, $quality, $explanation] = match ([$key, $definition->selectedValue]) {
                ['unspecified_availability', 'eligible_unless_blocked'], ['unspecified_availability', 'explicit_availability_required'] => [true, true, true, true],
                ['structural_hours_allocation', 'equal_per_eligible_doctor'], ['structural_hours_allocation', 'proportional_to_target_hours'] => [true, true, true, true],
                ['required_hours_meaning', 'roster_period_clinical_duty_target'], ['required_hours_meaning', 'roster_period_minimum_obligation'], ['required_hours_meaning', 'planning_reference_only'] => [true, true, true, true],
                ['target_hours_enforcement', 'informational'], ['target_hours_enforcement', 'soft_warning'] => [true, true, true, true],
                ['weekend_distribution', 'informational'], ['consecutive_day_limit', 'informational'], ['preference_strength', 'soft_preference'], ['employment_type_eligibility', 'all_active_types_eligible'] => [true, true, true, true],
                ['weekend_distribution', 'warning'], ['weekend_distribution', 'mandatory'] => [false, false, true, true],
                ['consecutive_day_limit', 'warning'], ['consecutive_day_limit', 'hard_limit'] => [false, false, true, true],
                ['target_hours_enforcement', 'hard_minimum'], ['target_hours_enforcement', 'hard_maximum'] => [false, false, true, true],
                ['preference_strength', 'preferred_off_prohibition'], ['employment_type_eligibility', 'explicit_availability_by_type'] => [false, false, true, true],
                default => [false, false, false, true],
            };
            $ready = $generator && $validator && $quality;
            $entries[] = ['policy_key' => $key, 'selected_value' => $definition->selectedValue, 'resolver' => true, 'generator' => $generator, 'validator' => $validator, 'quality' => $quality, 'explanation' => $explanation, 'enforcement_ready' => $ready];
        }
        usort($entries, fn (array $left, array $right): int => $left['policy_key'] <=> $right['policy_key']);

        return $entries;
    }
}
