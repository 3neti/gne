<?php

use App\Application\Rostering\ConfirmRosterPolicyCalibration;
use App\Application\Rostering\ResolveRosterPolicy;
use App\Domain\Rostering\RosterPolicyEvaluationContext;
use App\Domain\Rostering\RosterPolicyEvaluationPurpose;
use App\Domain\Rostering\RosterPolicyStatus;
use App\Models\RosterPolicyCalibration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function policyContext(string $date): RosterPolicyEvaluationContext
{
    return new RosterPolicyEvaluationContext(CarbonImmutable::parse($date), RosterPolicyEvaluationPurpose::HistoricalReplay);
}

function calibration(array $overrides): RosterPolicyCalibration
{
    return RosterPolicyCalibration::factory()->create(array_merge([
        'policy_key' => 'structural_hours_allocation',
        'status' => RosterPolicyStatus::Confirmed,
        'selected_value' => 'equal_per_eligible_doctor',
        'revision' => 1,
        'effective_from' => '2026-01-01',
        'decision_authority' => 'Anaesthesia Department Chair',
    ], $overrides));
}

test('future latest revision does not hide current revision or change its fingerprint', function () {
    calibration([]);
    $resolver = app(ResolveRosterPolicy::class);
    $before = $resolver->handle(policyContext('2026-08-05'));
    calibration(['revision' => 2, 'selected_value' => 'proportional_to_target_hours', 'effective_from' => '2026-09-01']);

    $today = $resolver->handle(policyContext('2026-08-05'));
    $future = $resolver->handle(policyContext('2026-09-01'));

    expect($today->policies['structural_hours_allocation']->revision)->toBe(1)
        ->and($today->fingerprint)->toBe($before->fingerprint)
        ->and($today->futurePolicies)->toHaveCount(1)
        ->and($future->policies['structural_hours_allocation']->revision)->toBe(2)
        ->and($future->fingerprint)->not->toBe($today->fingerprint);
});

test('expired and rejected records never become effective and fallback remains visibly provisional', function () {
    calibration(['effective_until' => '2026-06-30']);
    calibration(['revision' => 2, 'status' => RosterPolicyStatus::Rejected, 'selected_value' => 'proportional_to_target_hours', 'effective_from' => '2026-07-01']);

    $resolved = app(ResolveRosterPolicy::class)->handle(policyContext('2026-08-05'));

    expect($resolved->policies['structural_hours_allocation']->status)->toBe(RosterPolicyStatus::Provisional)
        ->and($resolved->policies['structural_hours_allocation']->effectiveState)->toBe('provisional')
        ->and($resolved->expiredPolicies)->toHaveCount(1);
});

test('superseded revision remains available for its historical effective window only', function () {
    calibration(['status' => RosterPolicyStatus::Superseded, 'effective_until' => '2026-07-31']);
    calibration(['revision' => 2, 'selected_value' => 'proportional_to_target_hours', 'effective_from' => '2026-08-01']);

    $historical = app(ResolveRosterPolicy::class)->handle(policyContext('2026-07-15'));
    $current = app(ResolveRosterPolicy::class)->handle(policyContext('2026-08-05'));

    expect($historical->policies['structural_hours_allocation']->revision)->toBe(1)
        ->and($current->policies['structural_hours_allocation']->revision)->toBe(2);
});

test('confirmation atomically closes the earlier window and rejects a conflicting scheduled window', function () {
    $actor = User::factory()->create(['is_roster_administrator' => true]);
    calibration([]);

    $new = app(ConfirmRosterPolicyCalibration::class)->handle($actor, ['policy_key' => 'structural_hours_allocation', 'selected_value' => 'proportional_to_target_hours', 'effective_from' => '2026-09-01', 'effective_until' => null, 'decision_authority' => 'Department Chair', 'source_reference' => 'Meeting 2026-08', 'notes' => 'Supersede from September.']);
    $prior = RosterPolicyCalibration::query()->where('revision', 1)->sole();

    expect($new->revision)->toBe(2)
        ->and($prior->fresh()->status)->toBe(RosterPolicyStatus::Superseded)
        ->and($prior->fresh()->effective_until->toDateString())->toBe('2026-08-31');

    app(ConfirmRosterPolicyCalibration::class)->handle($actor, ['policy_key' => 'structural_hours_allocation', 'selected_value' => 'equal_per_eligible_doctor', 'effective_from' => '2026-10-01', 'effective_until' => null, 'decision_authority' => 'Department Chair', 'source_reference' => 'Meeting 2026-09', 'notes' => 'Later decision.']);
    expect(fn () => app(ConfirmRosterPolicyCalibration::class)->handle($actor, ['policy_key' => 'structural_hours_allocation', 'selected_value' => 'proportional_to_target_hours', 'effective_from' => '2026-09-15', 'effective_until' => '2026-10-15', 'decision_authority' => 'Department Chair', 'source_reference' => 'Conflicting meeting', 'notes' => 'Conflict.']))->toThrow(DomainException::class);
});

test('eight provisional policies are reported as eight pending department decisions', function () {
    $status = app(ResolveRosterPolicy::class)->handle(policyContext('2026-08-05'))->calibrationStatus();

    expect($status->toArray()['pending_department_decisions'])->toBe(8)
        ->and($status->unresolvedMandatory)->toBe([])
        ->and($status->unresolvedQuality)->toBe([]);
});
