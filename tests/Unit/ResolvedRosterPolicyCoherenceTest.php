<?php

use App\Application\Rostering\EvaluateRosterPolicySetAgainstPeriod;
use App\Application\Rostering\ResolveRosterPolicy;
use App\Application\Rostering\ValidateResolvedRosterPolicyCoherence;
use App\Domain\Rostering\RequiredHoursPolicyCompatibilityMatrix;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyEnforcementReadinessStatus;
use App\Domain\Rostering\RosterPolicyStatus;
use Tests\TestCase;

uses(TestCase::class);

function policySet(array $selections = [], array $configurations = [], RosterPolicyStatus $status = RosterPolicyStatus::Confirmed): ResolvedRosterPolicy
{
    $base = app(ResolveRosterPolicy::class)->repositoryFallback();
    $policies = collect($base->policies)->map(function (RosterPolicyDefinition $definition, string $key) use ($selections, $configurations, $status): RosterPolicyDefinition {
        $selectedValue = $selections[$key] ?? $definition->selectedValue;
        $option = collect($definition->options)->firstWhere('value', $selectedValue);
        $configuration = [...($option?->fixedConfiguration ?? []), ...($configurations[$key] ?? $definition->configuration)];

        return new RosterPolicyDefinition($definition->identifier, $definition->key, $definition->revision, $status, $selectedValue, '2026-08-01', 'Anaesthesia Department', $definition->sourceReference, $definition->question, $definition->generationImpact, null, 'current', true, $definition->options, $configuration);
    })->all();

    return new ResolvedRosterPolicy($base->profileIdentifier, $base->revision, $base->generatorName, $base->generatorVersion, $base->provenance, $base->fingerprint, $policies, $base->evaluationContext);
}

function feasiblePeriodInput(int $required = 1, string $preference = 'neutral'): RosterGenerationInput
{
    $days = [['date' => '2026-08-01', 'required' => $required], ['date' => '2026-08-02', 'required' => $required]];
    $doctors = [
        ['identifier' => 'DOCTOR-A', 'name' => 'Ana Example', 'employment_type' => 'full_time', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'],
        ['identifier' => 'DOCTOR-B', 'name' => 'Ben Example', 'employment_type' => 'locum', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'],
    ];
    $availability = [];
    foreach ($days as $day) {
        foreach ($doctors as $doctor) {
            $availability[] = ['doctor_identifier' => $doctor['identifier'], 'date' => $day['date'], 'effective_status' => 'available', 'explicit_availability' => false, 'preference' => $preference];
        }
    }

    return new RosterGenerationInput('ROSTER-PERIOD-TEST', 'ready_for_generation', $days, $doctors, $availability, 0, 'sha256:'.str_repeat('a', 64));
}

it('defines nine compatible and three conflicting orthogonal hours combinations deterministically', function () {
    $matrix = new RequiredHoursPolicyCompatibilityMatrix;

    $first = $matrix->entries();
    $second = $matrix->entries();

    expect($first)->toBe($second)
        ->and(collect($first)->where('compatible', true))->toHaveCount(9)
        ->and(collect($first)->where('compatible', false))->toHaveCount(3)
        ->and($matrix->compatible('roster_period_minimum_obligation', 'hard_minimum'))->toBeTrue()
        ->and($matrix->compatible('roster_period_minimum_obligation', 'hard_maximum'))->toBeFalse()
        ->and($matrix->compatible('planning_reference_only', 'hard_minimum'))->toBeFalse();
});

it('classifies a complete supported policy set as ready for enforcement', function () {
    $policy = policySet(configurations: ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true]]);

    $result = app(ValidateResolvedRosterPolicyCoherence::class)->handle($policy, feasiblePeriodInput());

    expect($result->status)->toBe(RosterPolicyEnforcementReadinessStatus::ReadyForEnforcement)
        ->and($result->activationStatus)->toBe('confirmed_and_enforceable')
        ->and($result->periodFeasibility?->isFeasible())->toBeTrue();
});

it('rejects contradictory required-hours policy combinations', function (string $meaning, string $enforcement) {
    $policy = policySet(
        ['required_hours_meaning' => $meaning, 'target_hours_enforcement' => $enforcement],
        ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true], 'target_hours_enforcement' => ['excess_tolerance_hours' => 0]],
    );

    $result = app(ValidateResolvedRosterPolicyCoherence::class)->handle($policy);

    expect($result->status)->toBe(RosterPolicyEnforcementReadinessStatus::PolicyConflict)
        ->and(collect($result->findings)->pluck('code'))->toContain('POLICY_REQUIRED_HOURS_CONFLICT');
})->with([
    ['roster_period_minimum_obligation', 'hard_maximum'],
    ['planning_reference_only', 'hard_minimum'],
]);

it('excludes discovery-only holiday metadata from enforcement identity', function () {
    $first = policySet(configurations: ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true, 'holiday_effect' => 'reduces_target']]);
    $second = policySet(configurations: ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true, 'holiday_effect' => 'does_not_reduce_target']]);

    expect($first->enforcementFingerprint())->toBe($second->enforcementFingerprint());
});

it('reports explicit availability as period infeasible without mutating period input', function () {
    $policy = policySet(
        ['unspecified_availability' => 'explicit_availability_required', 'employment_type_eligibility' => 'explicit_availability_by_type'],
        ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true], 'employment_type_eligibility' => ['explicit_availability_required_for' => ['locum']]],
    );
    $input = feasiblePeriodInput();
    $before = $input->toArray();

    $result = app(EvaluateRosterPolicySetAgainstPeriod::class)->handle($policy, $input);

    expect($result->isFeasible())->toBeFalse()
        ->and($result->infeasibleDates)->toBe(['2026-08-01', '2026-08-02'])
        ->and($result->affectedDoctors)->toBe(['DOCTOR-A', 'DOCTOR-B'])
        ->and($input->toArray())->toBe($before);
});

it('reports a hard hours maximum below staffing demand as period infeasible', function () {
    $policy = policySet(
        ['target_hours_enforcement' => 'hard_maximum'],
        ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true], 'target_hours_enforcement' => ['excess_tolerance_hours' => 0]],
    );

    $result = app(ValidateResolvedRosterPolicyCoherence::class)->handle($policy, feasiblePeriodInput(required: 2));

    expect($result->status)->toBe(RosterPolicyEnforcementReadinessStatus::PeriodInfeasible)
        ->and(collect($result->findings)->pluck('code'))->toContain('POLICY_HOURS_CAP_BELOW_STAFFING_DEMAND');
});

it('reports hard consecutive enforcement and preferred-off prohibition honestly', function () {
    $unsupported = policySet(
        ['consecutive_day_limit' => 'hard_limit'],
        ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true], 'consecutive_day_limit' => ['maximum_consecutive_days' => 4]],
    );
    $preferredOff = policySet(
        ['preference_strength' => 'preferred_off_prohibition'],
        ['required_hours_meaning' => ['leave_reduces_target' => true, 'education_counts' => true]],
    );

    expect(app(ValidateResolvedRosterPolicyCoherence::class)->handle($unsupported)->status)->toBe(RosterPolicyEnforcementReadinessStatus::UnsupportedDependency)
        ->and(app(EvaluateRosterPolicySetAgainstPeriod::class)->handle($preferredOff, feasiblePeriodInput(preference: 'preferred_off'))->isFeasible())->toBeFalse();
});
