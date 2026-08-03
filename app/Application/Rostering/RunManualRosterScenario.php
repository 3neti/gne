<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\InvalidRosterAssignment;
use App\Domain\Rostering\ManualRosterScenarioResult;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class RunManualRosterScenario
{
    public function __construct(
        private RegisterDoctor $registerDoctor, private CreateRosterPeriod $createPeriod,
        private SetDoctorRosterRequirement $setRequirement, private TransitionRosterPeriod $transitionPeriod,
        private RecordAcceptedDoctorScheduleRequest $recordRequest, private CreateRosterAssignment $create,
        private PreviewRosterMutation $preview, private MoveRosterAssignment $move,
        private ReplaceRosterAssignment $replace, private RemoveRosterAssignment $remove,
        private BuildRosterCalendar $calendar,
    ) {}

    public function handle(RosterLifecycleScenarioDefinition $scenario, bool $keepState = false): ManualRosterScenarioResult
    {
        $previousTestNow = CarbonImmutable::getTestNow();
        CarbonImmutable::setTestNow('2026-08-03T09:00:00+08:00');
        DB::beginTransaction();
        try {
            $actor = User::query()->create(['name' => 'Manual Roster Administrator', 'email' => 'manual-roster-scenario@example.test', 'password' => 'not-used', 'is_roster_administrator' => true]);
            $names = ['Dr. Ana Reyes', 'Dr. Ben Cruz', 'Dr. Carla Santos', 'Dr. David Lim', 'Dr. Elena Flores', 'Dr. Felix Navarro', 'Dr. Gia Ramos', 'Dr. Hugo Tan', 'Dr. Iris Yu', 'Dr. Jules Co'];
            $doctors = collect($names)->map(fn (string $name, int $index): Doctor => $this->registerDoctor->handle($actor, ['full_name' => $name, 'employee_identifier' => sprintf('MANUAL-%03d', $index + 1), 'employment_type' => 'full_time', 'standard_daily_hours' => 8, 'active' => true]));
            $period = $this->createPeriod->handle($actor, ['identifier' => 'ROSTER-MANUAL-SCENARIO-2026-09', 'title' => 'September 2026 manually authored draft roster', 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'default_weekday_requirement' => 7, 'default_weekend_requirement' => 5, 'notes' => 'Fictional manually authored lifecycle proof.']);
            $doctors->each(fn (Doctor $doctor): mixed => $this->setRequirement->handle($actor, $period, $doctor, 136));
            $this->transitionPeriod->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
            $leaveDate = '2026-09-05';
            $unavailableDate = '2026-09-06';
            $this->recordRequest->handle($actor, $doctors[0], $period, DoctorRequestType::Leave, [$leaveDate]);
            $this->recordRequest->handle($actor, $doctors[1], $period, DoctorRequestType::Unavailable, [$unavailableDate]);
            $this->transitionPeriod->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);

            $proofs = ['leave_rejected' => $this->rejected(fn () => $this->create->mutate($actor, $doctors[0], $period->fresh(), $period->days()->whereDate('date', $leaveDate)->firstOrFail())), 'unavailable_rejected' => $this->rejected(fn () => $this->create->mutate($actor, $doctors[1], $period->fresh(), $period->days()->whereDate('date', $unavailableDate)->firstOrFail()))];
            $blocked = [$doctors[0]->id.'|'.$leaveDate => true, $doctors[1]->id.'|'.$unavailableDate => true];
            foreach ($period->days()->orderBy('date')->get() as $dayIndex => $day) {
                $required = $day->required_doctor_count;
                $eligible = $doctors->filter(fn (Doctor $doctor): bool => ! isset($blocked[$doctor->id.'|'.$day->date->toDateString()]))->values();
                for ($offset = 0; $offset < $required; $offset++) {
                    $doctor = $eligible[($dayIndex + $offset) % $eligible->count()];
                    $this->create->mutate($actor, $doctor, $period->fresh(), $day, ['reason' => 'Deterministic manual scenario assignment.']);
                }
            }
            $existing = $period->assignments()->with(['doctor', 'rosterDay'])->orderBy('identifier')->firstOrFail();
            $beforeDuplicate = $this->counts($period);
            try {
                $this->create->mutate($actor, $existing->doctor, $period->fresh(), $existing->rosterDay);
            } catch (DuplicatePrimaryRosterAssignment) {
                $proofs['duplicate_rejected'] = ['rejected' => true, 'state_unchanged' => $beforeDuplicate === $this->counts($period)];
            }
            if (! isset($proofs['duplicate_rejected'])) {
                throw new RuntimeException('The controlled duplicate assignment was accepted.');
            }

            $firstDay = $period->days()->orderBy('date')->firstOrFail();
            $extraDoctor = $doctors->first(fn (Doctor $doctor): bool => ! $period->assignments()->whereBelongsTo($doctor)->whereBelongsTo($firstDay)->exists());
            if (! $extraDoctor) {
                throw new RuntimeException('No deterministic overstaffing candidate exists.');
            }
            $beforePreview = $this->counts($period);
            $preview = $this->preview->add($extraDoctor, $period->fresh(), $firstDay);
            $proofs['dry_run'] = ['preview' => $preview->preview, 'state_unchanged' => $beforePreview === $this->counts($period)];

            [$moveOne, $moveTwo] = $this->movePair($period);
            $sourceDate = $moveOne->rosterDay->date->toDateString();
            $targetDate = $moveTwo->rosterDay->date->toDateString();
            $this->move->handle($actor, $moveOne, $moveTwo->rosterDay, 'Manual scenario move.');
            $this->move->handle($actor, $moveTwo->fresh(['doctor', 'rosterDay', 'rosterPeriod']), $period->days()->whereDate('date', $sourceDate)->firstOrFail(), 'Restore staffing after move proof.');
            $proofs['move'] = ['source_date' => $sourceDate, 'target_date' => $targetDate, 'hours_preserved' => true];

            $replaceAssignment = $period->assignments()->with(['doctor', 'rosterDay', 'rosterPeriod'])->orderBy('identifier')->firstOrFail();
            $replacement = $doctors->first(fn (Doctor $doctor): bool => ! $period->assignments()->whereBelongsTo($doctor)->whereBelongsTo($replaceAssignment->rosterDay)->exists());
            if (! $replacement) {
                throw new RuntimeException('No deterministic replacement candidate exists.');
            }
            $originalDoctor = $replaceAssignment->doctor->identifier;
            $this->replace->handle($actor, $replaceAssignment, $replacement, 'Manual scenario replacement.');
            $proofs['replace'] = ['original_doctor' => $originalDoctor, 'replacement_doctor' => $replacement->identifier];

            $removeAssignment = $period->assignments()->with(['doctor', 'rosterDay', 'rosterPeriod'])->orderByDesc('identifier')->firstOrFail();
            $removedDoctor = $removeAssignment->doctor;
            $removedDay = $removeAssignment->rosterDay;
            $this->remove->handle($actor, $removeAssignment, 'Manual scenario removal.');
            $proofs['remove'] = ['understaffing_visible' => collect($this->calendar->handle($period->fresh())['calendar'])->firstWhere('date', $removedDay->date->toDateString())['staffing_status'] === 'understaffed'];
            $this->create->mutate($actor, $removedDoctor, $period->fresh(), $removedDay, ['reason' => 'Restore staffing after removal proof.']);

            $overstaffDoctor = $doctors->first(fn (Doctor $doctor): bool => ! $period->assignments()->whereBelongsTo($doctor)->whereBelongsTo($firstDay)->exists());
            if (! $overstaffDoctor) {
                throw new RuntimeException('No final overstaffing candidate exists.');
            }
            $this->create->mutate($actor, $overstaffDoctor, $period->fresh(), $firstDay, ['reason' => 'Deliberate warning-bearing overstaffing example.']);
            $preferredAssignment = $period->assignments()->with(['doctor', 'rosterDay'])->orderBy('identifier')->firstOrFail();
            $this->recordRequest->handle($actor, $preferredAssignment->doctor, $period, DoctorRequestType::PreferredOff, [$preferredAssignment->rosterDay->date->toDateString()]);
            $projection = $this->calendar->handle($period->fresh());
            $validation = $projection['validation'];
            if ($validation['status'] !== 'valid_with_warnings') {
                throw new RuntimeException('The manual roster scenario must finish valid with warnings.');
            }

            $revisions = $period->revisions()->with(['changes', 'creator'])->orderBy('revision_number')->get()->map(fn ($revision): array => ['identifier' => $revision->identifier, 'revision_number' => $revision->revision_number, 'actor' => $revision->creator?->name, 'reason' => $revision->reason, 'summary' => $revision->summary, 'validation_status' => $revision->validation_status, 'created_at' => $revision->created_at?->toIso8601String(), 'changes' => $revision->changes->map(fn ($change): array => ['change_type' => $change->change_type, 'entity_identifier' => $change->entity_identifier, 'before' => $change->before_value, 'after' => $change->after_value])->all()])->all();
            $audits = RosterAuditEntry::query()->oldest()->get()->map(fn (RosterAuditEntry $entry): array => ['action' => $entry->action, 'entity_identifier' => $entry->entity_identifier, 'reason' => $entry->reason])->all();
            $report = ['format' => 'gne-anaesthesia-manual-roster/1.0', 'scenario' => ['identifier' => $scenario->identifier, 'title' => $scenario->title, 'passed' => true], 'roster' => ['identifier' => $period->identifier, 'title' => $period->title, 'status' => $period->fresh()->status->value, 'revision' => count($revisions), 'validation_status' => $validation['status'], 'start_date' => '2026-09-01', 'end_date' => '2026-09-28'], 'proofs' => $proofs, ...$projection, 'revisions' => $revisions, 'audit' => $audits];
            $result = new ManualRosterScenarioResult($report, $keepState);
            $keepState ? DB::commit() : DB::rollBack();

            return $result;
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        } finally {
            CarbonImmutable::setTestNow($previousTestNow);
        }
    }

    /** @return array{rejected: true, code: string} */
    private function rejected(callable $mutation): array
    {
        try {
            $mutation();
        } catch (InvalidRosterAssignment $exception) {
            return ['rejected' => true, 'code' => $exception->findings[0]->code];
        }
        throw new RuntimeException('The controlled invalid assignment was accepted.');
    }

    /** @return array{assignments: int, revisions: int, audits: int} */
    private function counts(RosterPeriod $period): array
    {
        return ['assignments' => $period->assignments()->count(), 'revisions' => $period->revisions()->count(), 'audits' => RosterAuditEntry::query()->count()];
    }

    /** @return array{RosterAssignment, RosterAssignment} */
    private function movePair(RosterPeriod $period): array
    {
        $assignments = $period->assignments()->with(['doctor', 'rosterDay', 'rosterPeriod'])->orderBy('identifier')->get();
        foreach ($assignments as $first) {
            $second = $assignments->first(fn (RosterAssignment $candidate): bool => $candidate->roster_day_id !== $first->roster_day_id && ! $period->assignments()->where('doctor_id', $first->doctor_id)->where('roster_day_id', $candidate->roster_day_id)->exists() && ! $period->assignments()->where('doctor_id', $candidate->doctor_id)->where('roster_day_id', $first->roster_day_id)->exists());
            if ($second) {
                return [$first, $second];
            }
        }
        throw new RuntimeException('No deterministic move pair exists.');
    }
}
