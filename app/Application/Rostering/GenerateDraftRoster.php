<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Contracts\Rostering\RosterGenerator;
use App\Domain\Rostering\InvalidRosterGeneration;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class GenerateDraftRoster
{
    public function __construct(private BuildRosterGenerationInput $input, private ResolveRosterPolicy $policy, private RosterGenerator $generator, private AnalyzeGeneratedRoster $analyze, private PersistGeneratedRosterBatch $persist, private ValidateRoster $validate, private CreateRosterRevision $revision, private RosterAuditRecorder $audit, private TransitionRosterPeriod $transition) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, RosterPeriod $period): array
    {
        if (! $actor->is_roster_administrator || $period->status !== RosterPeriodStatus::ReadyForGeneration || $period->assignments()->exists()) {
            throw new InvalidRosterGeneration('Initial draft generation requires an authorised administrator, ready_for_generation status, and an empty roster.');
        }
        $input = $this->input->handle($period);
        $policy = $this->policy->handle();
        $result = $this->analyze->handle($input, $this->generator->generate($input, $policy));
        if ($result->hasErrors()) {
            throw new InvalidRosterGeneration('Generated proposal contains mandatory errors and was not committed.');
        }

        return DB::transaction(function () use ($actor, $period, $input, $policy, $result): array {
            $batch = $this->persist->handle($actor, $period, $policy, $input, $result);
            $validation = $this->validate->handle($period->fresh());
            $summary = [...$result->toArray()['summary'], 'operation' => 'generate_initial_draft', 'generation_identifier' => $batch['run']->identifier, 'generator_name' => $policy->generatorName, 'generator_version' => $policy->generatorVersion];
            $revision = $this->revision->handle($actor, $period, 'Generated initial draft roster.', $summary, $validation, $batch['changes'], false);
            $this->audit->record($actor, 'roster_generation.completed', 'roster_generation_run', $batch['run']->identifier, null, ['roster_period_identifier' => $period->identifier, 'generator_name' => $policy->generatorName, 'generator_version' => $policy->generatorVersion, 'policy_fingerprint' => $policy->fingerprint, 'input_fingerprint' => $input->fingerprint, 'result_fingerprint' => $result->fingerprint, 'assignment_count' => count($batch['assignments']), 'revision_identifier' => $revision->identifier, 'validation_status' => $validation->status(), 'errors' => count($validation->errors()), 'warnings' => count($validation->warnings()), 'daily_staffing' => $result->dailyStaffing, 'doctor_hours' => $result->doctorHours], 'Generated initial draft roster.', $period);
            $this->transition->handle($actor, $period->fresh(), RosterPeriodStatus::Generated, 'Generated initial draft roster.');

            return ['generation_run' => $batch['run']->fresh(), 'revision' => $revision, 'result' => $result, 'validation' => $validation, 'period' => $period->fresh()];
        });
    }
}
