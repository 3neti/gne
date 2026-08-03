<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\AssignmentSource;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\RosterMutationResult;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ReplaceRosterAssignment
{
    public function __construct(private RosterAuditRecorder $audit, private ValidateRosterAssignment $validateAssignment, private ValidateRoster $validate, private CreateRosterRevision $createRevision, private BuildRosterMutationImpact $impact) {}

    public function handle(User $actor, RosterAssignment $assignment, Doctor $replacement, ?string $reason = null): RosterMutationResult
    {
        $assignment->loadMissing(['doctor', 'rosterDay', 'rosterPeriod']);
        $this->validateAssignment->handle($replacement, $assignment->rosterPeriod, $assignment->rosterDay);
        if (RosterAssignment::query()->whereKeyNot($assignment->id)->whereBelongsTo($replacement)->whereBelongsTo($assignment->rosterPeriod)->whereBelongsTo($assignment->rosterDay)->exists()) {
            throw new DuplicatePrimaryRosterAssignment('A primary assignment already exists for the replacement doctor, period, and day.');
        }

        return DB::transaction(function () use ($actor, $assignment, $replacement, $reason): RosterMutationResult {
            $before = $this->payload($assignment);
            $originalDoctor = $assignment->doctor->identifier;
            $assignment->update(['doctor_id' => $replacement->id, 'source' => AssignmentSource::ManuallyChanged, 'credited_hours' => $replacement->standard_daily_hours]);
            $assignment->load('doctor');
            $after = $this->payload($assignment);
            $this->audit->record($actor, 'roster_assignment.replaced', 'roster_assignment', $assignment->identifier, $before, $after, $reason ?? 'Assigned doctor replaced manually.');
            $validation = $this->validate->handle($assignment->rosterPeriod->fresh());
            $revision = $this->createRevision->handle($actor, $assignment->rosterPeriod, $reason ?? 'Assigned doctor replaced manually.', ['operation' => 'replace', 'from_doctor' => $originalDoctor, 'to_doctor' => $replacement->identifier, 'date' => $assignment->rosterDay->date->toDateString()], $validation, [['change_type' => 'replaced', 'entity_identifier' => $assignment->identifier, 'before_value' => $before, 'after_value' => $after]]);

            return new RosterMutationResult('replace', false, $assignment->fresh(['doctor', 'rosterDay']), $revision, $validation, $this->impact->handle($assignment->rosterPeriod, [$assignment->rosterDay->date->toDateString()], [$originalDoctor, $replacement->identifier]));
        });
    }

    /** @return array<string, mixed> */
    private function payload(RosterAssignment $assignment): array
    {
        return ['identifier' => $assignment->identifier, 'doctor_identifier' => $assignment->doctor->identifier, 'date' => $assignment->rosterDay->date->toDateString(), 'credited_hours' => $assignment->credited_hours, 'duty_code' => $assignment->duty_code->value, 'source' => $assignment->source->value];
    }
}
