<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\AssignmentSource;
use App\Domain\Rostering\AssignmentStatus;
use App\Domain\Rostering\DutyCode;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

final class CreateRosterAssignment
{
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

        return RosterAssignment::query()->create([
            'identifier' => 'ASSIGNMENT-'.Str::ulid(),
            'doctor_id' => $doctor->id,
            'roster_period_id' => $period->id,
            'roster_day_id' => $day->id,
            'status' => $status,
            'source' => $optional['source'] ?? AssignmentSource::Manual,
            'duty_code' => $optional['duty_code'] ?? ($status === AssignmentStatus::Assigned ? DutyCode::StandardDay : DutyCode::from($status->value)),
            'start_time' => $optional['start_time'] ?? null,
            'end_time' => $optional['end_time'] ?? null,
            'credited_hours' => $optional['credited_hours'] ?? ($status === AssignmentStatus::Assigned ? $doctor->standard_daily_hours : null),
            'notes' => $optional['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }
}
