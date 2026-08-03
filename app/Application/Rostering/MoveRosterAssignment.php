<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\AssignmentSource;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\RosterMutationResult;
use App\Models\RosterAssignment;
use App\Models\RosterDay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class MoveRosterAssignment
{
    public function __construct(private RosterAuditRecorder $audit, private ValidateRosterAssignment $validateAssignment, private ValidateRoster $validate, private CreateRosterRevision $createRevision, private BuildRosterMutationImpact $impact) {}

    public function handle(User $actor, RosterAssignment $assignment, RosterDay $targetDay, ?string $reason = null): RosterMutationResult
    {
        $assignment->loadMissing(['doctor', 'rosterDay', 'rosterPeriod']);
        $this->validateAssignment->handle($assignment->doctor, $assignment->rosterPeriod, $targetDay);
        if (RosterAssignment::query()->whereKeyNot($assignment->id)->whereBelongsTo($assignment->doctor)->whereBelongsTo($assignment->rosterPeriod)->whereBelongsTo($targetDay)->exists()) {
            throw new DuplicatePrimaryRosterAssignment('A primary assignment already exists for this doctor, period, and target day.');
        }

        return DB::transaction(function () use ($actor, $assignment, $targetDay, $reason): RosterMutationResult {
            $sourceDate = $assignment->rosterDay->date->toDateString();
            $before = $this->payload($assignment);
            $assignment->update(['roster_day_id' => $targetDay->id, 'source' => AssignmentSource::ManuallyChanged]);
            $assignment->load('rosterDay');
            $after = $this->payload($assignment);
            $this->audit->record($actor, 'roster_assignment.moved', 'roster_assignment', $assignment->identifier, $before, $after, $reason ?? 'Manual assignment moved.');
            $validation = $this->validate->handle($assignment->rosterPeriod->fresh());
            $revision = $this->createRevision->handle($actor, $assignment->rosterPeriod, $reason ?? 'Manual assignment moved.', ['operation' => 'move', 'doctor_identifier' => $assignment->doctor->identifier, 'from_date' => $sourceDate, 'to_date' => $targetDay->date->toDateString()], $validation, [['change_type' => 'moved', 'entity_identifier' => $assignment->identifier, 'before_value' => $before, 'after_value' => $after]]);

            return new RosterMutationResult('move', false, $assignment->fresh(['doctor', 'rosterDay']), $revision, $validation, $this->impact->handle($assignment->rosterPeriod, [$sourceDate, $targetDay->date->toDateString()], [$assignment->doctor->identifier]));
        });
    }

    /** @return array<string, mixed> */
    private function payload(RosterAssignment $assignment): array
    {
        return ['identifier' => $assignment->identifier, 'doctor_identifier' => $assignment->doctor->identifier, 'date' => $assignment->rosterDay->date->toDateString(), 'credited_hours' => $assignment->credited_hours, 'duty_code' => $assignment->duty_code->value, 'source' => $assignment->source->value];
    }
}
