<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterPolicyCoherenceFinding;
use App\Domain\Rostering\RosterPolicyPeriodFeasibility;

final readonly class EvaluateRosterPolicySetAgainstPeriod
{
    public function handle(ResolvedRosterPolicy $policy, RosterGenerationInput $input): RosterPolicyPeriodFeasibility
    {
        $availability = collect($input->availability)->keyBy(fn (array $cell): string => $cell['date'].'|'.$cell['doctor_identifier']);
        $globalExplicit = $policy->policies['unspecified_availability']->selectedValue === 'explicit_availability_required';
        $employment = $policy->policies['employment_type_eligibility'];
        $explicitCategories = $employment->selectedValue === 'explicit_availability_by_type' ? ($employment->configuration['explicit_availability_required_for'] ?? []) : [];
        $preferredOffProhibited = $policy->policies['preference_strength']->selectedValue === 'preferred_off_prohibition';
        $daily = [];
        $affectedDoctors = [];
        $preferredOffExclusions = 0;
        foreach ($input->days as $day) {
            $eligible = [];
            foreach ($input->doctors as $doctor) {
                $cell = $availability->get($day['date'].'|'.$doctor['identifier']);
                if (! is_array($cell) || in_array($cell['effective_status'], ['leave', 'unavailable'], true)) {
                    continue;
                }
                $requiresExplicit = $globalExplicit || in_array($doctor['employment_type'], $explicitCategories, true);
                if ($requiresExplicit && ! $cell['explicit_availability']) {
                    $affectedDoctors[] = $doctor['identifier'];

                    continue;
                }
                if ($preferredOffProhibited && $cell['preference'] === 'preferred_off') {
                    $preferredOffExclusions++;
                    $affectedDoctors[] = $doctor['identifier'];

                    continue;
                }
                $eligible[] = $doctor['identifier'];
            }
            $daily[] = ['date' => $day['date'], 'required_doctors' => $day['required'], 'eligible_doctors' => count($eligible), 'eligible_doctor_identifiers' => $eligible, 'feasible' => count($eligible) >= $day['required']];
        }
        $infeasibleDates = array_column(array_filter($daily, fn (array $day): bool => ! $day['feasible']), 'date');
        $dailyHours = (float) ($input->doctors[0]['standard_daily_hours'] ?? 8);
        $requiredStaffingHours = array_sum(array_map(fn (array $day): float => $day['required'] * $dailyHours, $input->days));
        $combinedTargetHours = array_sum(array_map(fn (array $doctor): float => (float) $doctor['required_hours'], $input->doctors));
        $enforcement = $policy->policies['target_hours_enforcement'];
        $combinedHoursCap = $enforcement->selectedValue === 'hard_maximum' ? $combinedTargetHours + (count($input->doctors) * (float) ($enforcement->configuration['excess_tolerance_hours'] ?? 0)) : null;
        $findings = [];
        if ($infeasibleDates !== []) {
            $findings[] = new RosterPolicyCoherenceFinding('error', 'POLICY_ELIGIBLE_POOL_BELOW_REQUIREMENT', 'The selected eligibility rules leave one or more dates below required staffing.', ['unspecified_availability', 'employment_type_eligibility', 'preference_strength'], [], $input->periodIdentifier, $infeasibleDates, array_values(array_unique($affectedDoctors)), 'Relax the exclusion rule, accept more availability, or change the staffing requirement.');
        }
        if ($combinedHoursCap !== null && $combinedHoursCap < $requiredStaffingHours) {
            $findings[] = new RosterPolicyCoherenceFinding('error', 'POLICY_HOURS_CAP_BELOW_STAFFING_DEMAND', 'No fully staffed roster can satisfy the confirmed hard maximums.', ['required_hours_meaning', 'target_hours_enforcement'], ['required_hours_meaning' => $policy->policies['required_hours_meaning']->selectedValue, 'target_hours_enforcement' => $enforcement->selectedValue], $input->periodIdentifier, array_column($input->days, 'date'), array_column($input->doctors, 'identifier'), 'Increase available target hours, increase tolerance, or reduce staffing demand.');
        }
        $unsupported = [];
        if ($policy->policies['consecutive_day_limit']->selectedValue === 'hard_limit') {
            $unsupported[] = 'hard_consecutive_day_generation_and_validation';
        }
        if ($policy->policies['weekend_distribution']->selectedValue === 'mandatory') {
            $unsupported[] = 'mandatory_weekend_generation_and_validation';
        }
        sort($affectedDoctors);
        sort($infeasibleDates);
        sort($unsupported);
        $canonical = ['period_identifier' => $input->periodIdentifier, 'policy_fingerprint' => $policy->enforcementFingerprint(), 'daily_eligibility' => $daily, 'required_staffing_hours' => $requiredStaffingHours, 'combined_target_hours' => $combinedTargetHours, 'combined_hours_cap' => $combinedHoursCap, 'preferred_off_exclusions' => $preferredOffExclusions, 'unsupported_dependencies' => $unsupported];

        return new RosterPolicyPeriodFeasibility($input->periodIdentifier, array_sum(array_column($daily, 'eligible_doctors')), $daily, $infeasibleDates, array_values(array_unique($affectedDoctors)), $requiredStaffingHours, $combinedTargetHours, $combinedHoursCap, $preferredOffExclusions, $unsupported, $findings, 'sha256:'.hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }
}
