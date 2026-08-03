<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\FoundationValidationSeverity;
use App\Domain\Rostering\InvalidRosterAssignment;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Domain\Rostering\RosterValidationFinding;
use App\Models\Doctor;
use App\Models\RosterDay;
use App\Models\RosterPeriod;

final readonly class ValidateRosterAssignment
{
    public function __construct(private ResolveDoctorAvailability $availability) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Doctor $doctor, RosterPeriod $period, RosterDay $day, array $attributes = []): void
    {
        $findings = [];
        $this->appendEditablePeriodFinding($period, $findings);
        if (! $doctor->active) {
            $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_DOCTOR_INACTIVE', 'Inactive doctors cannot receive roster assignments.', ['doctor_identifier' => $doctor->identifier]);
        }
        if ($day->roster_period_id !== $period->id || $day->date->lt($period->start_date) || $day->date->gt($period->end_date)) {
            $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_DAY_OUTSIDE_PERIOD', 'The assignment day must belong to and fall inside the roster period.', ['date' => $day->date->toDateString()]);
        }
        if (isset($attributes['credited_hours']) && (float) $attributes['credited_hours'] <= 0) {
            $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_CREDITED_HOURS_INVALID', 'Credited hours must be positive.');
        }
        if (($attributes['start_time'] ?? null) && ($attributes['end_time'] ?? null) && $attributes['end_time'] <= $attributes['start_time']) {
            $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_TIME_ORDER_INVALID', 'End time must be later than start time; overnight duties are deferred.');
        }
        if ($findings === [] && $day->roster_period_id === $period->id) {
            $cell = collect($this->availability->handle($period)['availability'])->first(fn (array $cell): bool => $cell['doctor_identifier'] === $doctor->identifier && $cell['date'] === $day->date->toDateString());
            if (in_array($cell['effective_status'] ?? null, ['leave', 'unavailable'], true)) {
                $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_BLOCKED_BY_REQUEST', 'Accepted leave or unavailability prohibits this assignment.', ['doctor_identifier' => $doctor->identifier, 'date' => $day->date->toDateString(), 'effective_status' => $cell['effective_status']]);
            }
        }

        if ($findings !== []) {
            throw new InvalidRosterAssignment($findings, $findings[0]->message);
        }
    }

    public function assertEditablePeriod(RosterPeriod $period): void
    {
        $findings = [];
        $this->appendEditablePeriodFinding($period, $findings);
        if ($findings !== []) {
            throw new InvalidRosterAssignment($findings, $findings[0]->message);
        }
    }

    /** @param list<RosterValidationFinding> $findings */
    private function appendEditablePeriodFinding(RosterPeriod $period, array &$findings): void
    {
        if (! in_array($period->status, [RosterPeriodStatus::ReadyForGeneration, RosterPeriodStatus::Generated, RosterPeriodStatus::UnderReview], true)) {
            $findings[] = new RosterValidationFinding(FoundationValidationSeverity::Error, 'ASSIGNMENT_PERIOD_NOT_EDITABLE', 'Manual assignments require a ready, generated, or under-review roster period.');
        }
    }
}
