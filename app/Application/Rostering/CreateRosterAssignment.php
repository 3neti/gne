<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\AssignmentSource;
use App\Domain\Rostering\AssignmentStatus;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\DutyCode;
use App\Domain\Rostering\RosterMutationResult;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class CreateRosterAssignment
{
    public function __construct(
        private RosterAuditRecorder $audit,
        private ValidateRosterAssignment $validateAssignment,
        private ValidateRoster $validateRoster,
        private CreateRosterRevision $createRevision,
        private TransitionRosterPeriod $transitionPeriod,
    ) {}

    /** @param array<string, mixed> $optional */
    public function handle(?User $actor, Doctor $doctor, RosterPeriod $period, RosterDay $day, AssignmentStatus $status = AssignmentStatus::Assigned, array $optional = []): RosterAssignment
    {
        return $this->mutate($actor, $doctor, $period, $day, $optional)->assignment;
    }

    /** @param array<string, mixed> $optional */
    public function mutate(?User $actor, Doctor $doctor, RosterPeriod $period, RosterDay $day, array $optional = []): RosterMutationResult
    {
        $this->validateAssignment->handle($doctor, $period, $day, $optional);
        if (RosterAssignment::query()->whereBelongsTo($doctor)->whereBelongsTo($period)->whereBelongsTo($day)->exists()) {
            throw new DuplicatePrimaryRosterAssignment('A primary assignment already exists for this doctor, period, and day.');
        }

        try {
            return DB::transaction(function () use ($actor, $doctor, $period, $day, $optional): RosterMutationResult {
                $assignment = RosterAssignment::query()->create(['identifier' => 'PENDING', 'doctor_id' => $doctor->id, 'roster_period_id' => $period->id, 'roster_day_id' => $day->id, 'status' => AssignmentStatus::Assigned, 'source' => $optional['source'] ?? AssignmentSource::ManuallyAdded, 'duty_code' => $optional['duty_code'] ?? DutyCode::StandardDay, 'start_time' => $optional['start_time'] ?? null, 'end_time' => $optional['end_time'] ?? null, 'credited_hours' => $optional['credited_hours'] ?? $doctor->standard_daily_hours, 'notes' => $optional['notes'] ?? null, 'created_by' => $actor?->id]);
                $assignment->update(['identifier' => sprintf('ASSIGNMENT-%06d', $assignment->id)]);
                $after = $this->payload($assignment->fresh(['doctor', 'rosterDay', 'rosterPeriod']));
                $this->audit->record($actor, 'roster_assignment.created', 'roster_assignment', $assignment->identifier, null, $after, $optional['reason'] ?? 'Manual assignment added.');
                if ($period->status === RosterPeriodStatus::ReadyForGeneration && $actor) {
                    $this->transitionPeriod->handle($actor, $period->fresh(), RosterPeriodStatus::Generated, 'First manual roster assignment created.');
                }
                $validation = $this->validateRoster->handle($period->fresh());
                $revision = $this->createRevision->handle($actor, $period, $optional['reason'] ?? 'Manual assignment added.', ['operation' => 'add', 'doctor_identifier' => $doctor->identifier, 'date' => $day->date->toDateString()], $validation, [['change_type' => 'added', 'entity_identifier' => $assignment->identifier, 'before_value' => null, 'after_value' => $after]]);

                return new RosterMutationResult('add', false, $assignment->fresh(['doctor', 'rosterDay']), $revision, $validation, $this->impact($period, $doctor, $day));
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'roster_assignments_primary_unique')) {
                throw new DuplicatePrimaryRosterAssignment('A primary assignment already exists for this doctor, period, and day.', previous: $exception);
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function payload(RosterAssignment $assignment): array
    {
        return ['identifier' => $assignment->identifier, 'doctor_identifier' => $assignment->doctor->identifier, 'roster_period_identifier' => $assignment->rosterPeriod->identifier, 'date' => $assignment->rosterDay->date->toDateString(), 'status' => $assignment->status->value, 'source' => $assignment->source->value, 'duty_code' => $assignment->duty_code->value, 'start_time' => $assignment->start_time, 'end_time' => $assignment->end_time, 'credited_hours' => $assignment->credited_hours, 'notes' => $assignment->notes];
    }

    /** @return array<string, mixed> */
    private function impact(RosterPeriod $period, Doctor $doctor, RosterDay $day): array
    {
        $assignedCount = $period->assignments()->whereBelongsTo($day)->count();
        $assignedHours = (float) $period->assignments()->whereBelongsTo($doctor)->sum('credited_hours');
        $requiredHours = (float) ($period->doctorRequirements()->whereBelongsTo($doctor)->value('required_hours') ?? 0);

        return ['affected_dates' => [$day->date->toDateString()], 'affected_doctors' => [$doctor->identifier], 'staffing_status' => $assignedCount < $day->required_doctor_count ? 'understaffed' : ($assignedCount > $day->required_doctor_count ? 'overstaffed' : 'fully_staffed'), 'assigned_count' => $assignedCount, 'required_count' => $day->required_doctor_count, 'doctor_hours_variance' => number_format($assignedHours - $requiredHours, 2, '.', '')];
    }
}
