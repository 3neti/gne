<?php

use App\Application\Rostering\CreateRosterAssignment;
use App\Application\Rostering\TransitionRosterPeriod;
use App\Application\Rostering\UpdateRosterPeriod;
use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;

it('blocks readiness only when foundation validation contains an error', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($actor);
    app(TransitionRosterPeriod::class)->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
    $period->days()->firstOrFail()->delete();
    $auditCount = RosterAuditEntry::query()->count();

    expect(fn () => app(TransitionRosterPeriod::class)->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration))
        ->toThrow(InvalidRosterTransition::class, 'validation errors');
    expect($period->fresh()->status)->toBe(RosterPeriodStatus::CollectingRequests)
        ->and(RosterAuditEntry::query()->count())->toBe($auditCount);
});

it('rolls back assignment creation when audit recording fails', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = readyFoundationPeriod($actor, createFoundationPeriod($actor));
    $audit = new class implements RosterAuditRecorder
    {
        public function record(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null, ?RosterPeriod $rosterPeriod = null): RosterAuditEntry
        {
            throw new RuntimeException('Audit unavailable.');
        }
    };

    app()->instance(RosterAuditRecorder::class, $audit);
    expect(fn () => app(CreateRosterAssignment::class)->handle($actor, $doctor, $period, $period->days->first()))
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect(RosterAssignment::query()->count())->toBe(0)
        ->and(RosterAuditEntry::query()->where('action', 'roster_assignment.created')->count())->toBe(0);
});

it('rejects duplicate assignments through the domain without a rejection audit', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = readyFoundationPeriod($actor, createFoundationPeriod($actor));
    $day = $period->days->first();
    app(CreateRosterAssignment::class)->handle($actor, $doctor, $period, $day);
    $auditCount = RosterAuditEntry::query()->count();

    expect(fn () => app(CreateRosterAssignment::class)->handle($actor, $doctor, $period, $day))->toThrow(DuplicatePrimaryRosterAssignment::class);
    expect(RosterAssignment::query()->count())->toBe(1)->and(RosterAuditEntry::query()->count())->toBe($auditCount);
});

it('allows roster period updates to change only title and notes', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($actor);

    $updated = app(UpdateRosterPeriod::class)->handle($actor, $period, ['title' => 'Allowed title', 'notes' => 'Allowed note', 'identifier' => 'FORBIDDEN', 'status' => RosterPeriodStatus::Published, 'start_date' => '2030-01-01']);

    expect($updated->title)->toBe('Allowed title')->and($updated->notes)->toBe('Allowed note')
        ->and($updated->identifier)->toBe('ROSTER-2026-09')->and($updated->status)->toBe(RosterPeriodStatus::Draft)
        ->and($updated->start_date->toDateString())->toBe('2026-09-01');
});

it('loads a closed allowlisted lifecycle scenario grammar', function () {
    $scenario = RosterLifecycleScenarioDefinition::fromFile(base_path('business/profiles/anaesthesia-rostering/scenarios/foundation-lifecycle.yaml'));

    expect($scenario->identifier)->toBe('ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE')
        ->and(array_column($scenario->steps, 'operation'))->toBe(['warning_readiness', 'error_readiness', 'assignment_audit', 'duplicate_rejection', 'audit_rollback']);
});

it('runs the lifecycle proof deterministically and rolls state back by default', function () {
    $before = [User::query()->count(), Doctor::query()->count(), RosterAssignment::query()->count()];

    $this->artisan('gne:roster:lifecycle:run', ['--json' => true])->assertSuccessful();

    expect([User::query()->count(), Doctor::query()->count(), RosterAssignment::query()->count()])->toBe($before);
});

it('rejects an unknown lifecycle scenario', function () {
    $this->artisan('gne:roster:lifecycle:run', ['--scenario' => 'UNKNOWN'])->assertFailed();
});
