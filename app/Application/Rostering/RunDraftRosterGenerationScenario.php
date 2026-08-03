<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\ManualRosterScenarioResult;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class RunDraftRosterGenerationScenario
{
    public function __construct(private RegisterDoctor $registerDoctor, private CreateRosterPeriod $createPeriod, private SetDoctorRosterRequirement $setRequirement, private TransitionRosterPeriod $transition, private RecordAcceptedDoctorScheduleRequest $recordRequest, private PreviewDraftRosterGeneration $preview, private GenerateDraftRoster $generate, private MoveRosterAssignment $move, private BuildRosterCalendar $calendar, private ListRosterPeriodAuditEntries $audit) {}

    public function handle(RosterLifecycleScenarioDefinition $scenario, bool $keepState = false): ManualRosterScenarioResult
    {
        $previous = CarbonImmutable::getTestNow();
        CarbonImmutable::setTestNow('2026-08-03T09:00:00+08:00');
        DB::beginTransaction();
        try {
            $actor = User::query()->create(['name' => 'Draft Generation Administrator', 'email' => 'draft-generation@example.test', 'password' => 'not-used', 'is_roster_administrator' => true]);
            $actor->forceFill(['is_roster_administrator' => true])->save();
            $names = ['Ana Reyes', 'Ben Cruz', 'Carla Santos', 'David Lim', 'Elena Flores', 'Felix Navarro', 'Gia Ramos', 'Hugo Tan', 'Iris Yu', 'Jules Co'];
            $doctors = collect($names)->map(fn (string $name, int $index): Doctor => $this->registerDoctor->handle($actor, ['full_name' => 'Dr. '.$name, 'employee_identifier' => sprintf('GENERATED-%03d', $index + 1), 'employment_type' => 'full_time', 'standard_daily_hours' => 8, 'active' => true]));
            $period = $this->createPeriod->handle($actor, ['identifier' => 'ROSTER-GENERATED-SCENARIO-2026-09', 'title' => 'September 2026 generated draft roster', 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'default_weekday_requirement' => 7, 'default_weekend_requirement' => 5]);
            $doctors->each(fn (Doctor $doctor): mixed => $this->setRequirement->handle($actor, $period, $doctor, 136));
            $this->transition->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
            $this->recordRequest->handle($actor, $doctors[0], $period, DoctorRequestType::Leave, ['2026-09-08', '2026-09-09', '2026-09-10']);
            $this->recordRequest->handle($actor, $doctors[1], $period, DoctorRequestType::Unavailable, ['2026-09-05']);
            $this->recordRequest->handle($actor, $doctors[2], $period, DoctorRequestType::Available, ['2026-09-01']);
            $this->recordRequest->handle($actor, $doctors[3], $period, DoctorRequestType::PreferredWork, ['2026-09-02']);
            $this->recordRequest->handle($actor, $doctors[4], $period, DoctorRequestType::PreferredOff, ['2026-09-03']);
            $this->transition->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);
            $before = ['assignments' => $period->assignments()->count(), 'revisions' => $period->revisions()->count(), 'runs' => $period->generationRuns()->count(), 'audit' => $this->audit->handle($period)->count()];
            $previewOne = $this->preview->handle($period->fresh());
            $previewTwo = $this->preview->handle($period->fresh());
            $after = ['assignments' => $period->assignments()->count(), 'revisions' => $period->revisions()->count(), 'runs' => $period->generationRuns()->count(), 'audit' => $this->audit->handle($period)->count()];
            $committed = $this->generate->handle($actor, $period->fresh());
            $generatedValidation = $committed['validation']->toArray();
            $generationState = $this->calendar->handle($period->fresh());
            $assignment = $period->assignments()->with(['doctor', 'rosterDay', 'rosterPeriod'])->orderBy('identifier')->firstOrFail();
            $target = $period->days()->whereDoesntHave('assignments', fn ($query) => $query->whereBelongsTo($assignment->doctor))->orderBy('date')->firstOrFail();
            $manual = $this->move->handle($actor, $assignment, $target, 'Manual correction after generated draft.');
            $currentState = $this->calendar->handle($period->fresh());
            $report = ['format' => 'gne-anaesthesia-draft-generation/1.1', 'scenario' => ['identifier' => $scenario->identifier, 'title' => $scenario->title, 'passed' => true], 'roster' => ['identifier' => $period->identifier, 'status' => $period->fresh()->status->value], 'generator' => ['name' => 'balanced_greedy', 'version' => '1.0', 'policy_fingerprint' => $committed['generation_run']->policy_fingerprint, 'input_fingerprint' => $committed['generation_run']->input_fingerprint, 'generation_fingerprint' => $committed['result']->fingerprint], 'preview' => ['deterministic' => $previewOne->fingerprint === $previewTwo->fingerprint, 'state_unchanged' => $before === $after, ...$previewOne->toArray()], 'generation' => $committed['result']->toArray(), 'batch' => ['generation_runs' => $period->generationRuns()->count(), 'generation_revision' => $committed['revision']->identifier, 'revision_changes' => $committed['revision']->changes->count(), 'generation_audits' => $period->auditEntries()->where('action', 'roster_generation.completed')->count()], 'generated_validation' => $generatedValidation, 'manual_correction' => ['revision' => $manual->revision->revision_number, 'operation' => 'move', 'source' => $manual->assignment->source->value, 'validation' => $manual->validation->toArray()], 'generation_state' => $generationState, 'current_state' => $currentState];
            $result = new ManualRosterScenarioResult($report, $keepState);
            $keepState ? DB::commit() : DB::rollBack();

            return $result;
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        } finally {
            CarbonImmutable::setTestNow($previous);
        }
    }
}
