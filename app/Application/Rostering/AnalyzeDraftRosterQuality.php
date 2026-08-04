<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DraftRosterQualityResult;
use App\Domain\Rostering\GeneratedRosterResult;
use App\Domain\Rostering\RosterGenerationFeasibility;
use App\Domain\Rostering\RosterGenerationInput;
use Carbon\CarbonImmutable;

final readonly class AnalyzeDraftRosterQuality
{
    public function handle(RosterGenerationInput $input, GeneratedRosterResult $result, RosterGenerationFeasibility $feasibility): DraftRosterQualityResult
    {
        $doctorCount = max(count($input->doctors), 1);
        $structuralAllocation = (float) $feasibility->structuralHoursVariance / $doctorCount;
        $names = collect($input->doctors)->pluck('name', 'identifier');
        $assignments = collect($result->assignments);
        $hours = collect($result->doctorHours)->map(function (array $doctor) use ($structuralAllocation, $names, $feasibility): array {
            $rawVariance = (float) $doctor['variance'];
            $residual = $rawVariance - $structuralAllocation;
            $threshold = (float) ($feasibility->standardCreditedHoursPerSlot ?? 8);
            $status = match (true) {
                abs($residual) < 0.005 => $rawVariance > 0 ? 'above_target_due_to_structural_demand' : ($rawVariance < 0 ? 'below_target_due_to_structural_demand' : 'on_target'),
                abs($residual) <= $threshold => 'acceptable_residual_variance',
                default => 'material_residual_imbalance',
            };

            return [...$doctor, 'doctor_name' => $names->get($doctor['doctor_identifier']), 'raw_variance' => number_format($rawVariance, 2, '.', ''), 'allocated_structural_variance' => number_format($structuralAllocation, 2, '.', ''), 'residual_variance' => number_format($residual, 2, '.', ''), 'quality_status' => $status];
        })->values();
        $weekends = collect($input->doctors)->map(function (array $doctor) use ($assignments): array {
            $doctorAssignments = $assignments->where('doctor_identifier', $doctor['identifier']);
            $saturday = $doctorAssignments->filter(fn (array $assignment): bool => CarbonImmutable::parse($assignment['date'])->isSaturday())->count();
            $sunday = $doctorAssignments->filter(fn (array $assignment): bool => CarbonImmutable::parse($assignment['date'])->isSunday())->count();

            return ['doctor_identifier' => $doctor['identifier'], 'doctor_name' => $doctor['name'], 'weekday_assignments' => $doctorAssignments->count() - $saturday - $sunday, 'saturday_assignments' => $saturday, 'sunday_assignments' => $sunday, 'weekend_assignments' => $saturday + $sunday];
        })->values();
        $patterns = collect($input->doctors)->map(function (array $doctor) use ($assignments): array {
            $dates = $assignments->where('doctor_identifier', $doctor['identifier'])->pluck('date')->sort()->values();
            $completedRuns = [];
            $currentRun = 0;
            $previous = null;
            foreach ($dates as $date) {
                if ($previous !== null && CarbonImmutable::parse($previous)->addDay()->toDateString() === $date) {
                    $currentRun++;
                } else {
                    if ($currentRun > 1) {
                        $completedRuns[] = $currentRun;
                    }
                    $currentRun = 1;
                }
                $previous = $date;
            }
            if ($currentRun > 1) {
                $completedRuns[] = $currentRun;
            }

            return ['doctor_identifier' => $doctor['identifier'], 'doctor_name' => $doctor['name'], 'maximum_consecutive_assigned_days' => max($completedRuns ?: [$currentRun]), 'consecutive_day_runs' => count($completedRuns)];
        })->values();
        $cells = collect($input->availability)->keyBy(fn (array $cell): string => $cell['doctor_identifier'].'|'.$cell['date']);
        $explicitAssignments = $assignments->filter(fn (array $assignment): bool => (bool) ($cells->get($assignment['doctor_identifier'].'|'.$assignment['date'])['explicit_availability'] ?? false))->count();
        $explicitRequests = collect($input->availability)->where('explicit_availability', true);
        $explicitHonored = $explicitRequests->filter(fn (array $cell): bool => $assignments->contains(fn (array $assignment): bool => $assignment['doctor_identifier'] === $cell['doctor_identifier'] && $assignment['date'] === $cell['date']))->count();
        $rawVariances = $hours->pluck('raw_variance')->map(fn (string $value): float => (float) $value);
        $residuals = $hours->pluck('residual_variance')->map(fn (string $value): float => (float) $value);
        $assignedHours = $hours->pluck('assigned_hours')->map(fn (string $value): float => (float) $value)->sort()->values();
        $assignmentCounts = $hours->pluck('assignment_count');
        $weekendCounts = $weekends->pluck('weekend_assignments');
        $preferences = collect($result->preferences);
        $fullyStaffed = collect($result->dailyStaffing)->where('status', 'fully_staffed')->count();
        $allStaffed = $fullyStaffed === count($result->dailyStaffing);
        $residualRange = ($residuals->max() ?? 0) - ($residuals->min() ?? 0);
        $classification = match (true) {
            $result->hasErrors() => 'invalid',
            $allStaffed && abs($residualRange) < 0.005 => 'balanced_within_feasibility',
            $residualRange <= (float) ($feasibility->standardCreditedHoursPerSlot ?? 8) => 'acceptable_with_warnings',
            default => 'materially_imbalanced',
        };
        $findings = [];
        if ((float) $feasibility->structuralHoursVariance > 0) {
            $findings[] = ['severity' => 'warning', 'code' => 'TARGET_HOURS_BELOW_STAFFING_DEMAND', 'message' => $feasibility->explanation];
        }
        foreach ($hours->where('quality_status', 'material_residual_imbalance') as $doctor) {
            $findings[] = ['severity' => 'warning', 'code' => 'DOCTOR_RESIDUAL_HOURS_IMBALANCE', 'doctor_identifier' => $doctor['doctor_identifier'], 'message' => 'Assigned hours differ materially from the feasibility-adjusted fair share.'];
        }

        return new DraftRosterQualityResult($classification, $hours->all(), $weekends->all(), $patterns->all(), ['minimum_assigned_hours' => number_format((float) ($assignedHours->min() ?? 0), 2, '.', ''), 'maximum_assigned_hours' => number_format((float) ($assignedHours->max() ?? 0), 2, '.', ''), 'assigned_hours_range' => number_format((float) (($assignedHours->max() ?? 0) - ($assignedHours->min() ?? 0)), 2, '.', ''), 'mean_assigned_hours' => number_format((float) $assignedHours->avg(), 2, '.', ''), 'median_assigned_hours' => number_format((float) $assignedHours->median(), 2, '.', ''), 'raw_variance_range' => number_format((float) (($rawVariances->max() ?? 0) - ($rawVariances->min() ?? 0)), 2, '.', ''), 'residual_variance_range' => number_format((float) $residualRange, 2, '.', ''), 'assignment_count_range' => (int) (($assignmentCounts->max() ?? 0) - ($assignmentCounts->min() ?? 0)), 'weekend_assignment_range' => (int) (($weekendCounts->max() ?? 0) - ($weekendCounts->min() ?? 0)), 'preferred_work_honored' => $preferences->where('type', 'preferred_work')->where('honored', true)->count(), 'preferred_work_unhonored' => $preferences->where('type', 'preferred_work')->where('honored', false)->count(), 'preferred_work_fulfillment_rate' => $this->rate($preferences->where('type', 'preferred_work')->where('honored', true)->count(), $preferences->where('type', 'preferred_work')->count()), 'preferred_off_honored' => $preferences->where('type', 'preferred_off')->where('honored', true)->count(), 'preferred_off_violated' => $preferences->where('type', 'preferred_off')->where('honored', false)->count(), 'maximum_consecutive_assigned_days' => (int) ($patterns->max('maximum_consecutive_assigned_days') ?? 0), 'fully_staffed_dates' => $fullyStaffed], ['explicit_availability_requests' => $explicitRequests->count(), 'honored_explicit_availability_requests' => $explicitHonored, 'unhonored_explicit_availability_requests' => $explicitRequests->count() - $explicitHonored, 'explicit_availability_assignments' => $explicitAssignments, 'unspecified_eligibility_assignments' => $assignments->count() - $explicitAssignments, 'explicit_availability_fulfillment_rate' => $this->rate($explicitHonored, $explicitRequests->count())], $findings, ['Consecutive-day reporting is descriptive only; no fatigue or rest-period policy is enforced.', 'Unspecified active doctors remain provisionally eligible.', 'The deterministic greedy generator does not claim mathematical optimality.', 'One primary standard-day assignment per doctor and date is modeled.']);
    }

    private function rate(int $honored, int $total): string
    {
        return $total === 0 ? 'not_applicable' : number_format(($honored / $total) * 100, 2, '.', '').'%';
    }
}
