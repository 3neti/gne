<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\RosterMutationResult;
use App\Models\RosterAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RemoveRosterAssignment
{
    public function __construct(private RosterAuditRecorder $audit, private ValidateRosterAssignment $validateAssignment, private ValidateRoster $validate, private CreateRosterRevision $createRevision, private BuildRosterMutationImpact $impact) {}

    public function handle(User $actor, RosterAssignment $assignment, ?string $reason = null): RosterMutationResult
    {
        $assignment->loadMissing('rosterPeriod');
        $this->validateAssignment->assertEditablePeriod($assignment->rosterPeriod);

        return DB::transaction(function () use ($actor, $assignment, $reason): RosterMutationResult {
            $assignment->loadMissing(['doctor', 'rosterDay', 'rosterPeriod']);
            $period = $assignment->rosterPeriod;
            $before = $this->payload($assignment);
            $date = $assignment->rosterDay->date->toDateString();
            $doctor = $assignment->doctor->identifier;
            $identifier = $assignment->identifier;
            $assignment->delete();
            $this->audit->record($actor, 'roster_assignment.removed', 'roster_assignment', $identifier, $before, null, $reason ?? 'Manual assignment removed.');
            $validation = $this->validate->handle($period->fresh());
            $revision = $this->createRevision->handle($actor, $period, $reason ?? 'Manual assignment removed.', ['operation' => 'remove', 'doctor_identifier' => $doctor, 'date' => $date], $validation, [['change_type' => 'removed', 'entity_identifier' => $identifier, 'before_value' => $before, 'after_value' => null]]);

            return new RosterMutationResult('remove', false, null, $revision, $validation, $this->impact->handle($period, [$date], [$doctor]));
        });
    }

    /** @return array<string, mixed> */
    private function payload(RosterAssignment $assignment): array
    {
        return ['identifier' => $assignment->identifier, 'doctor_identifier' => $assignment->doctor->identifier, 'date' => $assignment->rosterDay->date->toDateString(), 'credited_hours' => $assignment->credited_hours, 'duty_code' => $assignment->duty_code->value, 'notes' => $assignment->notes];
    }
}
