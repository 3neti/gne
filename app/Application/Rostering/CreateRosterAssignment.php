<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\AssignmentSource;
use App\Domain\Rostering\AssignmentStatus;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\DutyCode;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateRosterAssignment
{
    public function __construct(private RecordRosterAudit $audit) {}

    /** @param array<string, mixed> $optional */
    public function handle(?User $actor, Doctor $doctor, RosterPeriod $period, RosterDay $day, AssignmentStatus $status = AssignmentStatus::Assigned, array $optional = []): RosterAssignment
    {
        if (! $doctor->active) {
            throw new DomainException('Inactive doctors cannot receive new roster assignments.');
        }
        if ($day->roster_period_id !== $period->id || $day->date->lt($period->start_date) || $day->date->gt($period->end_date)) {
            throw new DomainException('The assignment day must belong to and fall inside the roster period.');
        }
        if (isset($optional['credited_hours']) && (float) $optional['credited_hours'] < 0) {
            throw new DomainException('Credited hours cannot be negative.');
        }

        if (RosterAssignment::query()->whereBelongsTo($doctor)->whereBelongsTo($period)->whereBelongsTo($day)->exists()) {
            throw new DuplicatePrimaryRosterAssignment('A primary assignment already exists for this doctor, period, and day.');
        }

        try {
            return DB::transaction(function () use ($actor, $doctor, $period, $day, $status, $optional): RosterAssignment {
                $assignment = RosterAssignment::query()->create([
                    'identifier' => 'ASSIGNMENT-'.Str::ulid(), 'doctor_id' => $doctor->id, 'roster_period_id' => $period->id,
                    'roster_day_id' => $day->id, 'status' => $status, 'source' => $optional['source'] ?? AssignmentSource::Manual,
                    'duty_code' => $optional['duty_code'] ?? ($status === AssignmentStatus::Assigned ? DutyCode::StandardDay : DutyCode::from($status->value)),
                    'start_time' => $optional['start_time'] ?? null, 'end_time' => $optional['end_time'] ?? null,
                    'credited_hours' => $optional['credited_hours'] ?? ($status === AssignmentStatus::Assigned ? $doctor->standard_daily_hours : null),
                    'notes' => $optional['notes'] ?? null, 'created_by' => $actor?->id,
                ]);
                $this->audit->handle($actor, 'roster_assignment.created', 'roster_assignment', $assignment->identifier, null, [
                    'identifier' => $assignment->identifier, 'doctor_identifier' => $doctor->identifier,
                    'roster_period_identifier' => $period->identifier, 'date' => $day->date->toDateString(),
                    'status' => $assignment->status->value, 'source' => $assignment->source->value,
                    'duty_code' => $assignment->duty_code->value, 'credited_hours' => $assignment->credited_hours,
                ]);

                return $assignment;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'roster_assignments_primary_unique')) {
                throw new DuplicatePrimaryRosterAssignment('A primary assignment already exists for this doctor, period, and day.', previous: $exception);
            }

            throw $exception;
        }
    }
}
