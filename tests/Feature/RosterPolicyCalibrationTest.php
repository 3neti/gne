<?php

use App\Application\Rostering\ResolveRosterPolicy;
use App\Models\RosterPolicyCalibration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('roster administrator can view explicit provisional calibration status', function () {
    $this->withoutVite();
    $administrator = User::factory()->create(['is_roster_administrator' => true]);

    $this->actingAs($administrator)->get(route('rostering.policy_calibration.index'))->assertOk()->assertInertia(fn ($page) => $page->component('rostering/PolicyCalibration')->where('policy.calibration.provisional', fn ($items) => count($items) === 8));
});

test('ordinary user cannot view or confirm policy calibration', function () {
    $user = User::factory()->create(['is_roster_administrator' => false]);

    $this->actingAs($user)->get(route('rostering.policy_calibration.index'))->assertForbidden();
    $this->actingAs($user)->post(route('rostering.policy_calibration.store'), [])->assertForbidden();
});

test('confirmation creates an audited immutable revision and changes the effective fingerprint', function () {
    $administrator = User::factory()->create(['is_roster_administrator' => true]);
    $before = app(ResolveRosterPolicy::class)->handle()->fingerprint;

    $this->actingAs($administrator)->post(route('rostering.policy_calibration.store'), ['policy_key' => 'structural_hours_allocation', 'selected_value' => 'proportional_to_target_hours', 'effective_from' => '2026-08-04', 'source_reference' => 'Anaesthesia policy meeting 2026-08-04', 'notes' => 'Department selected target-hour proportional allocation.'])->assertRedirect();

    $record = RosterPolicyCalibration::query()->sole();
    expect($record->status->value)->toBe('confirmed')->and($record->revision)->toBe(1)->and(app(ResolveRosterPolicy::class)->handle()->fingerprint)->not->toBe($before);
    $this->assertDatabaseHas('roster_audit_entries', ['action' => 'roster_policy.confirmed', 'entity_identifier' => $record->identifier]);
});
