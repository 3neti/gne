<?php

use App\Application\Rostering\ConfirmRosterPolicyCalibration;
use App\Application\Rostering\ResolveRosterPolicy;
use App\Application\Rostering\SelectOperationalRosterPolicy;
use App\Application\Rostering\ValidateRosterPolicyConfirmation;
use App\Domain\Rostering\InvalidRosterPolicyConfirmation;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterPolicyConfirmability;
use App\Domain\Rostering\RosterPolicyConfirmationValidation;
use App\Domain\Rostering\RosterPolicyEvaluationContext;
use App\Domain\Rostering\RosterPolicyEvaluationPurpose;
use App\Models\RosterPolicyCalibration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function confirmationValidation(string $policyKey, string $option, array $configuration = []): RosterPolicyConfirmationValidation
{
    $definition = app(ResolveRosterPolicy::class)->handle()->policies[$policyKey];

    return app(ValidateRosterPolicyConfirmation::class)->handle($definition, $option, $configuration);
}

test('registered policy options expose typed confirmability metadata', function () {
    $policy = app(ResolveRosterPolicy::class)->handle();
    $options = collect($policy->policies)->flatMap->options;

    expect($options)->not->toBeEmpty()
        ->and($options->every(fn ($option): bool => is_bool($option->supported) && is_array($option->parameters)))->toBeTrue()
        ->and(confirmationValidation('weekend_distribution', 'informational')->confirmability)->toBe(RosterPolicyConfirmability::Confirmable)
        ->and(confirmationValidation('weekend_distribution', 'warning')->confirmability)->toBe(RosterPolicyConfirmability::ConfigurationRequired)
        ->and(confirmationValidation('weekend_distribution', 'warning', ['maximum_weekend_difference' => 2, 'day_grouping' => 'combined'])->confirmability)->toBe(RosterPolicyConfirmability::Confirmable)
        ->and(confirmationValidation('weekend_distribution', 'mandatory', ['maximum_weekend_difference' => -1, 'day_grouping' => 'separate'])->confirmability)->toBe(RosterPolicyConfirmability::Invalid);
});

test('typed parameters reject omissions unknown values and wrong types', function () {
    expect(confirmationValidation('consecutive_day_limit', 'hard_limit')->missingParameters)->toBe(['maximum_consecutive_days'])
        ->and(confirmationValidation('consecutive_day_limit', 'hard_limit', ['maximum_consecutive_days' => 0])->confirmability)->toBe(RosterPolicyConfirmability::Invalid)
        ->and(confirmationValidation('employment_type_eligibility', 'explicit_availability_by_type')->confirmability)->toBe(RosterPolicyConfirmability::ConfigurationRequired)
        ->and(confirmationValidation('employment_type_eligibility', 'explicit_availability_by_type', ['explicit_availability_required_for' => ['locum', 'visiting']])->configuration['explicit_availability_required_for'])->toBe(['locum', 'visiting'])
        ->and(confirmationValidation('employment_type_eligibility', 'explicit_availability_by_type', ['explicit_availability_required_for' => ['contractor']])->confirmability)->toBe(RosterPolicyConfirmability::Invalid)
        ->and(confirmationValidation('weekend_distribution', 'warning', ['maximum_weekend_difference' => 'two', 'day_grouping' => 'combined'])->confirmability)->toBe(RosterPolicyConfirmability::Invalid)
        ->and(confirmationValidation('weekend_distribution', 'informational', ['invented' => true])->invalidParameters)->toHaveKey('invented');
});

test('unsupported options are recordable but unresolved options cannot be confirmed', function () {
    expect(confirmationValidation('target_hours_enforcement', 'soft_warning')->confirmability)->toBe(RosterPolicyConfirmability::Confirmable)
        ->and(confirmationValidation('target_hours_enforcement', 'hard_maximum', ['excess_tolerance_hours' => 0])->confirmability)->toBe(RosterPolicyConfirmability::Confirmable)
        ->and(confirmationValidation('target_hours_enforcement', 'authorized_excess')->confirmability)->toBe(RosterPolicyConfirmability::UnsupportedInCurrentRelease)
        ->and(confirmationValidation('target_hours_enforcement', 'overtime_based')->unsupportedDependencies)->toBe(['overtime_model'])
        ->and(fn () => app(ValidateRosterPolicyConfirmation::class)->confirmable(app(ResolveRosterPolicy::class)->handle()->policies['target_hours_enforcement'], 'unresolved', []))->toThrow(InvalidRosterPolicyConfirmation::class);
});

test('a confirmed unsupported decision remains visible and uses the explicit operational fallback', function () {
    $actor = User::factory()->create(['is_roster_administrator' => true]);
    app(ConfirmRosterPolicyCalibration::class)->handle($actor, ['policy_key' => 'target_hours_enforcement', 'selected_value' => 'authorized_excess', 'effective_from' => '2026-08-01', 'decision_authority' => 'Department Chair', 'source_reference' => 'Future policy meeting', 'notes' => 'Record intent without activation.']);
    $input = new RosterGenerationInput('ROSTER-PERIOD-TEST', 'ready_for_generation', [], [], [], 0, 'sha256:test');

    $selection = app(SelectOperationalRosterPolicy::class)->handle(new RosterPolicyEvaluationContext(CarbonImmutable::parse('2026-08-06'), RosterPolicyEvaluationPurpose::GenerationPreview, 'ROSTER-PERIOD-TEST'), $input);

    expect($selection->confirmedPolicy->policies['target_hours_enforcement']->selectedValue)->toBe('authorized_excess')
        ->and($selection->readiness->activationStatus)->toBe('confirmed_but_not_enforceable')
        ->and($selection->usesFallback)->toBeTrue()
        ->and($selection->operationalPolicy->policies['target_hours_enforcement']->selectedValue)->toBe('soft_warning');
});

test('normalized configuration changes identity but key order does not', function () {
    $definition = app(ResolveRosterPolicy::class)->handle()->policies['weekend_distribution'];
    $first = app(ValidateRosterPolicyConfirmation::class)->confirmable($definition, 'warning', ['day_grouping' => 'combined', 'maximum_weekend_difference' => '2']);
    $same = app(ValidateRosterPolicyConfirmation::class)->confirmable($definition, 'warning', ['maximum_weekend_difference' => 2, 'day_grouping' => 'combined']);
    $changed = app(ValidateRosterPolicyConfirmation::class)->confirmable($definition, 'warning', ['maximum_weekend_difference' => 3, 'day_grouping' => 'combined']);

    expect($first->configuration)->toBe($same->configuration)->not->toBe($changed->configuration);
});

test('permanent supersession never resumes an expired prior revision', function () {
    $actor = User::factory()->create(['is_roster_administrator' => true]);
    $confirm = app(ConfirmRosterPolicyCalibration::class);
    $confirm->handle($actor, ['policy_key' => 'structural_hours_allocation', 'selected_value' => 'equal_per_eligible_doctor', 'effective_from' => '2026-01-01', 'decision_authority' => 'Chair', 'source_reference' => 'Meeting 1', 'notes' => 'First decision.']);
    $confirm->handle($actor, ['policy_key' => 'structural_hours_allocation', 'selected_value' => 'proportional_to_target_hours', 'effective_from' => '2026-09-01', 'effective_until' => '2026-09-30', 'decision_authority' => 'Chair', 'source_reference' => 'Meeting 2', 'notes' => 'Permanent supersession.']);

    $prior = RosterPolicyCalibration::query()->where('revision', 1)->sole();
    $september = app(ResolveRosterPolicy::class)->handle(new RosterPolicyEvaluationContext(CarbonImmutable::parse('2026-09-15'), RosterPolicyEvaluationPurpose::HistoricalReplay));
    $october = app(ResolveRosterPolicy::class)->handle(new RosterPolicyEvaluationContext(CarbonImmutable::parse('2026-10-01'), RosterPolicyEvaluationPurpose::HistoricalReplay));

    expect($prior->fresh()->effective_until?->toDateString())->toBe('2026-08-31')
        ->and($september->policies['structural_hours_allocation']->selectedValue)->toBe('proportional_to_target_hours')
        ->and($october->policies['structural_hours_allocation']->selectedValue)->toBe('equal_per_eligible_doctor')
        ->and($october->policies['structural_hours_allocation']->isProvisional())->toBeTrue()
        ->and($september->fingerprint)->not->toBe($october->fingerprint);
});

test('calibration page exposes dynamic configuration and unsupported choices', function () {
    $this->withoutVite();
    $administrator = User::factory()->create(['is_roster_administrator' => true]);

    $this->actingAs($administrator)->get(route('rostering.policy_calibration.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('policy.policies.weekend_distribution.options.1.parameters.0.key', 'maximum_weekend_difference')
        ->where('policy.policies.target_hours_enforcement.options.4.supported', false)
        ->where('policy.policies.structural_hours_allocation.configuration.remainder_distribution', 'largest_fractional_remainder_then_stable_doctor_identity'));
});

test('application service rejects incomplete configuration and persists normalized typed values', function () {
    $actor = User::factory()->create(['is_roster_administrator' => true]);
    $confirm = app(ConfirmRosterPolicyCalibration::class);
    $data = ['policy_key' => 'weekend_distribution', 'selected_value' => 'warning', 'effective_from' => '2026-08-06', 'decision_authority' => 'Department Chair', 'source_reference' => 'Policy meeting', 'notes' => 'Weekend threshold decision.'];

    expect(fn () => $confirm->handle($actor, $data))->toThrow(InvalidRosterPolicyConfirmation::class, 'maximum_weekend_difference');
    $record = $confirm->handle($actor, [...$data, 'configuration' => ['day_grouping' => 'combined', 'maximum_weekend_difference' => '2']]);

    expect($record->configuration)->toBe(['day_grouping' => 'combined', 'maximum_weekend_difference' => 2]);
});
