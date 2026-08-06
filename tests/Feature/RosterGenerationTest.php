<?php

use App\Application\Rostering\GenerateDraftRoster;
use App\Application\Rostering\PreviewDraftRosterGeneration;
use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\InvalidRosterGeneration;
use App\Models\Doctor;
use App\Models\DoctorRosterRequirement;
use App\Models\RosterAuditEntry;
use App\Models\RosterDay;
use App\Models\RosterGenerationRun;
use App\Models\RosterPeriod;
use App\Models\RosterRevision;
use App\Models\User;

function generationFixture(): array
{
    $actor = User::factory()->rosterAdministrator()->create();
    $period = RosterPeriod::factory()->create(['identifier' => 'ROSTER-GENERATE', 'start_date' => '2026-09-01', 'end_date' => '2026-09-02', 'status' => 'ready_for_generation', 'created_by' => $actor]);
    RosterDay::factory()->create(['roster_period_id' => $period, 'date' => '2026-09-01', 'required_doctor_count' => 1]);
    RosterDay::factory()->create(['roster_period_id' => $period, 'date' => '2026-09-02', 'required_doctor_count' => 1]);
    $doctors = collect(['DOCTOR-A', 'DOCTOR-B'])->map(fn (string $identifier): Doctor => Doctor::factory()->create(['identifier' => $identifier, 'standard_daily_hours' => 8]));
    $doctors->each(fn (Doctor $doctor) => DoctorRosterRequirement::factory()->create(['doctor_id' => $doctor, 'roster_period_id' => $period, 'required_hours' => 8]));

    return [$actor, $period];
}

it('previews deterministically without durable effects', function () {
    [$actor, $period] = generationFixture();
    $first = app(PreviewDraftRosterGeneration::class)->handle($period);
    $second = app(PreviewDraftRosterGeneration::class)->handle($period);

    expect($first->fingerprint)->toBe($second->fingerprint)
        ->and($first->assignments)->toHaveCount(2)
        ->and($first->feasibility?->classification)->toBe('targets_exactly_match_demand')
        ->and($first->quality?->classification)->toBe('balanced_within_feasibility')
        ->and($period->assignments()->count())->toBe(0)
        ->and(RosterGenerationRun::count())->toBe(0)
        ->and(RosterRevision::count())->toBe(0)
        ->and(RosterAuditEntry::where('action', 'roster_generation.completed')->count())->toBe(0)
        ->and($period->fresh()->status->value)->toBe('ready_for_generation');
});

it('keeps unrelated doctors out of generation identity and rejects an existing roster body', function () {
    [$actor, $period] = generationFixture();
    $before = app(PreviewDraftRosterGeneration::class)->handle($period);
    Doctor::factory()->create(['identifier' => 'DOCTOR-UNRELATED']);
    $after = app(PreviewDraftRosterGeneration::class)->handle($period);
    $period->assignments()->create(['identifier' => 'ASSIGNMENT-EXISTING', 'doctor_id' => Doctor::where('identifier', 'DOCTOR-A')->value('id'), 'roster_day_id' => $period->days()->value('id'), 'source' => 'manually_added', 'status' => 'assigned', 'duty_code' => 'standard_day', 'credited_hours' => 8]);

    expect($after->fingerprint)->toBe($before->fingerprint)
        ->and(fn () => app(PreviewDraftRosterGeneration::class)->handle($period))->toThrow(InvalidRosterGeneration::class, 'cannot overwrite');
});

it('commits one atomic generation run revision and audit then transitions lifecycle', function () {
    [$actor, $period] = generationFixture();
    $result = app(GenerateDraftRoster::class)->handle($actor, $period);

    expect($period->assignments()->count())->toBe(2)
        ->and($period->assignments()->where('source', 'generated')->count())->toBe(2)
        ->and(RosterGenerationRun::count())->toBe(1)
        ->and($result['generation_run']->policy_evaluation_date->toDateString())->toBe('2026-09-01')
        ->and($result['generation_run']->policy_snapshot['fingerprint'])->toBe($result['generation_run']->policy_fingerprint)
        ->and($result['generation_run']->policy_snapshot['evaluation_context']['purpose'])->toBe('generation_commit')
        ->and($result['generation_run']->policy_snapshot['policies']['structural_hours_allocation']['configuration'])->toMatchArray(['rounding_unit' => 'hundredth_hour', 'remainder_distribution' => 'largest_fractional_remainder_then_stable_doctor_identity'])
        ->and(RosterRevision::count())->toBe(1)
        ->and($result['revision']->changes)->toHaveCount(2)
        ->and(RosterAuditEntry::where('action', 'roster_generation.completed')->count())->toBe(1)
        ->and(RosterAuditEntry::where('action', 'roster_revision.created')->count())->toBe(0)
        ->and($period->fresh()->status->value)->toBe('generated');
});

it('rolls back the complete generation batch when generation audit persistence fails', function () {
    [$actor, $period] = generationFixture();
    $this->app->instance(RosterAuditRecorder::class, new class implements RosterAuditRecorder
    {
        public function record(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null, ?RosterPeriod $rosterPeriod = null): RosterAuditEntry
        {
            throw new RuntimeException('Injected audit failure.');
        }
    });

    expect(fn () => app(GenerateDraftRoster::class)->handle($actor, $period))->toThrow(RuntimeException::class, 'Injected audit failure.')
        ->and($period->assignments()->count())->toBe(0)
        ->and($period->generationRuns()->count())->toBe(0)
        ->and($period->revisions()->count())->toBe(0)
        ->and($period->fresh()->status->value)->toBe('ready_for_generation');
});

it('allows administrators to inspect generation and denies ordinary users', function () {
    [$actor, $period] = generationFixture();
    $ordinary = User::factory()->create();

    $this->actingAs($actor)->get(route('rostering.periods.generation.show', $period))->assertOk()->assertInertia(fn ($page) => $page->component('rostering/periods/Generation')->where('readiness.doctor_count', 2)->where('feasibility.classification', 'targets_exactly_match_demand'));
    $this->actingAs($ordinary)->get(route('rostering.periods.generation.show', $period))->assertForbidden();
});
