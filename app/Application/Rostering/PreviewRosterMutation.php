<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\AssignmentSource;
use App\Domain\Rostering\AssignmentStatus;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\DutyCode;
use App\Domain\Rostering\RosterMutationResult;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use Illuminate\Support\Facades\DB;

final readonly class PreviewRosterMutation
{
    public function __construct(private ValidateRosterAssignment $validateAssignment, private ValidateRoster $validateRoster, private BuildRosterMutationImpact $impact) {}

    /** @param array<string, mixed> $attributes */
    public function add(Doctor $doctor, RosterPeriod $period, RosterDay $day, array $attributes = []): RosterMutationResult
    {
        $this->validateAssignment->handle($doctor, $period, $day, $attributes);
        $this->assertNoDuplicate($doctor, $period, $day);

        return $this->rolledBack(function () use ($doctor, $period, $day, $attributes): RosterMutationResult {
            RosterAssignment::query()->create([
                'identifier' => 'PREVIEW-ASSIGNMENT', 'doctor_id' => $doctor->id, 'roster_period_id' => $period->id,
                'roster_day_id' => $day->id, 'status' => AssignmentStatus::Assigned,
                'source' => AssignmentSource::ManuallyAdded, 'duty_code' => $attributes['duty_code'] ?? DutyCode::StandardDay,
                'start_time' => $attributes['start_time'] ?? null, 'end_time' => $attributes['end_time'] ?? null,
                'credited_hours' => $attributes['credited_hours'] ?? $doctor->standard_daily_hours, 'notes' => $attributes['notes'] ?? null,
            ]);
            $validation = $this->validateRoster->handle($period->fresh());

            return new RosterMutationResult('add', true, null, null, $validation, $this->impact->handle($period, [$day->date->toDateString()], [$doctor->identifier]));
        });
    }

    public function remove(RosterAssignment $assignment): RosterMutationResult
    {
        $assignment->loadMissing(['doctor', 'rosterDay', 'rosterPeriod']);
        $this->validateAssignment->assertEditablePeriod($assignment->rosterPeriod);

        return $this->rolledBack(function () use ($assignment): RosterMutationResult {
            $period = $assignment->rosterPeriod;
            $date = $assignment->rosterDay->date->toDateString();
            $doctor = $assignment->doctor->identifier;
            $assignment->delete();
            $validation = $this->validateRoster->handle($period->fresh());

            return new RosterMutationResult('remove', true, null, null, $validation, $this->impact->handle($period, [$date], [$doctor]));
        });
    }

    public function move(RosterAssignment $assignment, RosterDay $targetDay): RosterMutationResult
    {
        $assignment->loadMissing(['doctor', 'rosterDay', 'rosterPeriod']);
        $this->validateAssignment->handle($assignment->doctor, $assignment->rosterPeriod, $targetDay);
        $this->assertNoDuplicate($assignment->doctor, $assignment->rosterPeriod, $targetDay, $assignment);

        return $this->rolledBack(function () use ($assignment, $targetDay): RosterMutationResult {
            $sourceDate = $assignment->rosterDay->date->toDateString();
            $assignment->update(['roster_day_id' => $targetDay->id]);
            $validation = $this->validateRoster->handle($assignment->rosterPeriod->fresh());

            return new RosterMutationResult('move', true, null, null, $validation, $this->impact->handle($assignment->rosterPeriod, [$sourceDate, $targetDay->date->toDateString()], [$assignment->doctor->identifier]));
        });
    }

    public function replace(RosterAssignment $assignment, Doctor $replacement): RosterMutationResult
    {
        $assignment->loadMissing(['doctor', 'rosterDay', 'rosterPeriod']);
        $this->validateAssignment->handle($replacement, $assignment->rosterPeriod, $assignment->rosterDay);
        $this->assertNoDuplicate($replacement, $assignment->rosterPeriod, $assignment->rosterDay, $assignment);

        return $this->rolledBack(function () use ($assignment, $replacement): RosterMutationResult {
            $originalDoctor = $assignment->doctor->identifier;
            $assignment->update(['doctor_id' => $replacement->id, 'credited_hours' => $replacement->standard_daily_hours]);
            $validation = $this->validateRoster->handle($assignment->rosterPeriod->fresh());

            return new RosterMutationResult('replace', true, null, null, $validation, $this->impact->handle($assignment->rosterPeriod, [$assignment->rosterDay->date->toDateString()], [$originalDoctor, $replacement->identifier]));
        });
    }

    private function assertNoDuplicate(Doctor $doctor, RosterPeriod $period, RosterDay $day, ?RosterAssignment $except = null): void
    {
        $query = RosterAssignment::query()->whereBelongsTo($doctor)->whereBelongsTo($period)->whereBelongsTo($day);
        if ($except) {
            $query->whereKeyNot($except->id);
        }
        if ($query->exists()) {
            throw new DuplicatePrimaryRosterAssignment('A primary assignment already exists for this doctor, period, and day.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function rolledBack(callable $callback): mixed
    {
        DB::beginTransaction();
        try {
            return $callback();
        } finally {
            DB::rollBack();
        }
    }
}
