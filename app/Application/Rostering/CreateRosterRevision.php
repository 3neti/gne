<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\RosterValidationResult;
use App\Models\RosterPeriod;
use App\Models\RosterRevision;
use App\Models\User;

final readonly class CreateRosterRevision
{
    public function __construct(private RosterAuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array{change_type: string, entity_identifier: string, before_value: array<string, mixed>|null, after_value: array<string, mixed>|null}>  $changes
     */
    public function handle(?User $actor, RosterPeriod $period, string $reason, array $summary, RosterValidationResult $validation, array $changes): RosterRevision
    {
        $revisionNumber = (int) $period->revisions()->max('revision_number') + 1;
        $revision = RosterRevision::query()->create(['identifier' => sprintf('%s-REV-%04d', $period->identifier, $revisionNumber), 'roster_period_id' => $period->id, 'revision_number' => $revisionNumber, 'created_by' => $actor?->id, 'reason' => $reason, 'summary' => $summary, 'validation_status' => $validation->status(), 'validation_snapshot' => $validation->toArray()]);
        $revision->changes()->createMany($changes);
        $this->audit->record($actor, 'roster_revision.created', 'roster_revision', $revision->identifier, null, ['roster_period_identifier' => $period->identifier, 'revision_number' => $revisionNumber, 'summary' => $summary, 'validation_status' => $validation->status()], $reason, $period);

        return $revision->fresh(['changes', 'creator']);
    }
}
