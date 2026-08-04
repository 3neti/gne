<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\FoundationValidationSeverity;
use App\Domain\Rostering\RosterValidationFinding;
use App\Domain\Rostering\RosterValidationResult;
use App\Models\RosterPeriod;

final readonly class ValidateRoster
{
    public function __construct(private ResolveDoctorAvailability $availability, private BuildDoctorHoursSummary $hours, private BuildRosterGenerationInput $generationInput, private AnalyzeRosterGenerationFeasibility $feasibility, private ResolveRosterPolicy $resolvePolicy, private AllocateStructuralVariance $allocator) {}

    public function handle(RosterPeriod $period): RosterValidationResult
    {
        $period->load(['days', 'assignments.doctor', 'assignments.rosterDay', 'doctorRequirements.doctor', 'scheduleRequests.dates']);
        $availability = collect($this->availability->handle($period)['availability'])->keyBy(fn (array $cell): string => $cell['doctor_identifier'].'|'.$cell['date']);
        $policy = $this->resolvePolicy->handle();
        $findings = [];

        foreach ($period->assignments->sortBy('identifier') as $assignment) {
            $date = $assignment->rosterDay->date->toDateString();
            $context = ['assignment_identifier' => $assignment->identifier, 'doctor_identifier' => $assignment->doctor->identifier, 'date' => $date];
            if (! $assignment->doctor->active) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_DOCTOR_INACTIVE', 'An inactive doctor cannot remain assigned.', $context);
            }
            $cell = $availability->get($assignment->doctor->identifier.'|'.$date);
            if (in_array($cell['effective_status'] ?? null, ['leave', 'unavailable'], true)) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_BLOCKED_BY_REQUEST', 'The assignment conflicts with accepted leave or unavailability.', [...$context, 'effective_status' => $cell['effective_status']]);
            }
            if ($policy->unspecifiedAvailability()->value === 'explicit_availability_required' && ! ($cell['explicit_availability'] ?? false)) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_REQUIRES_EXPLICIT_AVAILABILITY', 'Department policy requires explicit accepted availability for assignment.', [...$context, 'policy_identifier' => $policy->policies['unspecified_availability']->identifier]);
            }
            if (($cell['preference'] ?? null) === 'preferred_off' || ($cell['preference'] ?? null) === 'conflicted') {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Warning, 'ASSIGNMENT_PREFERRED_OFF', 'The doctor is assigned despite a preferred-off request.', $context);
            }
        }

        foreach ($period->days->sortBy('date') as $day) {
            $assigned = $period->assignments->where('roster_day_id', $day->id)->count();
            $context = ['date' => $day->date->toDateString(), 'required' => $day->required_doctor_count, 'assigned' => $assigned];
            if ($assigned < $day->required_doctor_count) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ROSTER_DAY_UNDERSTAFFED', 'Assigned doctors are below the daily requirement.', $context);
            } elseif ($assigned > $day->required_doctor_count) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Warning, 'ROSTER_DAY_OVERSTAFFED', 'Assigned doctors exceed the daily requirement.', $context);
            }
        }

        if ($period->generationRuns()->exists()) {
            $input = $this->generationInput->handle($period);
            $feasibility = $this->feasibility->handle($input);
            $allocation = $this->allocator->handle($input, $feasibility, $policy);
            $threshold = (float) ($feasibility->standardCreditedHoursPerSlot ?? 8);
            if ((float) $feasibility->structuralHoursVariance > 0) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Warning, 'TARGET_HOURS_BELOW_STAFFING_DEMAND', $feasibility->explanation, ['structural_hours_variance' => $feasibility->structuralHoursVariance]);
            }
            if (! $allocation->isResolved()) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Warning, 'STRUCTURAL_ALLOCATION_POLICY_UNRESOLVED', $allocation->explanation, ['policy_identifier' => $allocation->policyIdentifier]);
            } else {
                foreach ($this->hours->handle($period) as $doctor) {
                    $structuralAllocation = (float) ($allocation->allocations[$doctor['doctor_identifier']] ?? 0);
                    $residual = (float) $doctor['variance'] - $structuralAllocation;
                    if (abs($residual) > $threshold) {
                        $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Warning, 'DOCTOR_RESIDUAL_HOURS_IMBALANCE', 'Assigned hours differ materially from the department policy-adjusted fair share.', ['doctor_identifier' => $doctor['doctor_identifier'], 'raw_variance' => $doctor['variance'], 'allocated_structural_variance' => number_format($structuralAllocation, 2, '.', ''), 'residual_variance' => number_format($residual, 2, '.', ''), 'policy_identifier' => $allocation->policyIdentifier]);
                    }
                }
            }
        } else {
            foreach ($this->hours->handle($period) as $doctor) {
                if ($doctor['status'] === 'below_target') {
                    $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Warning, 'DOCTOR_HOURS_BELOW_TARGET', 'Assigned hours are below the doctor target.', ['doctor_identifier' => $doctor['doctor_identifier'], 'variance' => $doctor['variance']]);
                } elseif ($doctor['status'] === 'above_target') {
                    $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Warning, 'DOCTOR_HOURS_ABOVE_TARGET', 'Assigned hours exceed the doctor target.', ['doctor_identifier' => $doctor['doctor_identifier'], 'variance' => $doctor['variance']]);
                }
            }
        }

        usort($findings, fn (RosterValidationFinding $left, RosterValidationFinding $right): int => [$left->severity === FoundationValidationSeverity::Error ? 0 : 1, (string) ($left->context['date'] ?? ''), (string) ($left->context['doctor_identifier'] ?? ''), $left->code] <=> [$right->severity === FoundationValidationSeverity::Error ? 0 : 1, (string) ($right->context['date'] ?? ''), (string) ($right->context['doctor_identifier'] ?? ''), $right->code]);

        return new RosterValidationResult($findings);
    }
}
