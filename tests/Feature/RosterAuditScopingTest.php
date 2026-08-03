<?php

use App\Application\Rostering\ListRosterPeriodAuditEntries;
use App\Application\Rostering\RecordRosterAudit;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores exact period scope and lists only the selected period deterministically', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $periodA = RosterPeriod::factory()->create(['identifier' => 'ROSTER-A']);
    $periodB = RosterPeriod::factory()->create(['identifier' => 'ROSTER-B']);
    $recorder = app(RecordRosterAudit::class);
    $recorder->record($actor, 'roster_assignment.created', 'roster_assignment', 'A-1', null, ['roster_period_id' => $periodA->id], rosterPeriod: $periodA);
    $recorder->record($actor, 'roster_assignment.moved', 'roster_assignment', 'A-2', null, ['roster_period_id' => $periodA->id], rosterPeriod: $periodA);
    $recorder->record($actor, 'roster_assignment.created', 'roster_assignment', 'B-1', null, ['roster_period_id' => $periodB->id], rosterPeriod: $periodB);
    $recorder->record($actor, 'doctor.updated', 'doctor', 'DOCTOR-UNSCOPED', null, ['active' => true]);

    $service = app(ListRosterPeriodAuditEntries::class);

    expect($service->handle($periodA)->pluck('entity_identifier')->all())->toBe(['A-1', 'A-2'])
        ->and($service->handle($periodB)->pluck('entity_identifier')->all())->toBe(['B-1'])
        ->and($service->handle($periodA, ['roster_assignment.moved'])->pluck('entity_identifier')->all())->toBe(['A-2'])
        ->and($service->handle($periodA, limit: 1, latestFirst: true)->pluck('entity_identifier')->all())->toBe(['A-2'])
        ->and(RosterAuditEntry::query()->where('entity_identifier', 'DOCTOR-UNSCOPED')->value('roster_period_id'))->toBeNull();
});

it('keeps operator and complete history routes owned by the selected period', function () {
    $this->withoutVite();
    $administrator = User::factory()->rosterAdministrator()->create();
    $periodA = RosterPeriod::factory()->create(['identifier' => 'ROSTER-A']);
    $periodB = RosterPeriod::factory()->create(['identifier' => 'ROSTER-B']);
    RosterAuditEntry::factory()->forRosterPeriod($periodA)->create(['entity_identifier' => 'A-AUDIT', 'action' => 'roster_assignment.created']);
    RosterAuditEntry::factory()->forRosterPeriod($periodB)->create(['entity_identifier' => 'B-AUDIT', 'action' => 'roster_assignment.created']);

    $this->actingAs($administrator)->get(route('rostering.periods.roster.show', $periodA))->assertOk()->assertInertia(fn ($page) => $page
        ->component('rostering/periods/Assignments')
        ->where('audit.0.entity_identifier', 'A-AUDIT')
        ->missing('audit.1'));
    $this->actingAs($administrator)->get(route('rostering.periods.audit.index', $periodA))->assertOk()->assertInertia(fn ($page) => $page
        ->component('rostering/periods/History')
        ->where('period.identifier', 'ROSTER-A')
        ->where('entries.0.entity_identifier', 'A-AUDIT')
        ->missing('entries.1'));
});

it('adds query indexes for exact period audit history', function () {
    $indexes = collect(Schema::getIndexes('roster_audit_entries'))->pluck('columns');

    expect($indexes)->toContain(['roster_period_id', 'created_at'], ['roster_period_id', 'action', 'created_at']);
});

it('backfills only deterministic roster period scope and leaves unresolved rows unscoped', function () {
    $period = RosterPeriod::factory()->create(['identifier' => 'ROSTER-BACKFILL']);
    DB::table('roster_audit_entries')->insert([
        ['action' => 'roster_period.created', 'entity_type' => 'roster_period', 'entity_identifier' => $period->identifier, 'created_at' => now(), 'updated_at' => now()],
        ['action' => 'doctor.updated', 'entity_type' => 'doctor', 'entity_identifier' => 'DOCTOR-LEGACY', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration = require database_path('migrations/2026_08_03_142821_backfill_roster_period_scope_on_roster_audit_entries.php');
    $migration->up();

    expect(RosterAuditEntry::query()->where('entity_identifier', 'ROSTER-BACKFILL')->value('roster_period_id'))->toBe($period->id)
        ->and(RosterAuditEntry::query()->where('entity_identifier', 'DOCTOR-LEGACY')->value('roster_period_id'))->toBeNull();
});
