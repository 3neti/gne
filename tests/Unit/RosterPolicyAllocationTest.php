<?php

use App\Application\Rostering\AllocateStructuralVariance;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationFeasibility;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyStatus;

function allocationPolicy(string $value): ResolvedRosterPolicy
{
    $definition = new RosterPolicyDefinition('POLICY-STRUCTURAL', 'structural_hours_allocation', 1, RosterPolicyStatus::Provisional, $value, null, 'Anaesthesia department', 'policy.yaml', 'How are extra hours shared?', 'quality');

    return new ResolvedRosterPolicy('PROFILE', 1, 'balanced_greedy', '1.0', ['policy.yaml'], 'sha256:policy', ['structural_hours_allocation' => $definition]);
}

function heterogeneousInput(): RosterGenerationInput
{
    return new RosterGenerationInput('PERIOD', 'ready_for_generation', [], [['identifier' => 'A', 'name' => 'Doctor A', 'required_hours' => '160', 'standard_daily_hours' => '8'], ['identifier' => 'B', 'name' => 'Doctor B', 'required_hours' => '80', 'standard_daily_hours' => '8']], [], 0, 'sha256:input');
}

function excessFeasibility(): RosterGenerationFeasibility
{
    return new RosterGenerationFeasibility('PERIOD', 33, '8.00', '264.00', '240.00', '24.00', 'staffing_demand_exceeds_targets', '24.00', '0.00', 2, [], 'Extra hours exist.');
}

test('equal structural allocation is deterministic for unequal targets', function () {
    $result = (new AllocateStructuralVariance)->handle(heterogeneousInput(), excessFeasibility(), allocationPolicy('equal_per_eligible_doctor'));

    expect($result->status)->toBe('resolved')->and($result->allocations)->toBe(['A' => '12.00', 'B' => '12.00']);
});

test('proportional structural allocation follows target hours and reconciles', function () {
    $result = (new AllocateStructuralVariance)->handle(heterogeneousInput(), excessFeasibility(), allocationPolicy('proportional_to_target_hours'));

    expect($result->allocations)->toBe(['A' => '16.00', 'B' => '8.00'])->and(array_sum(array_map('floatval', $result->allocations)))->toBe(24.0);
});

test('structural rounding uses largest fractional remainder then stable doctor identity', function () {
    $input = new RosterGenerationInput('PERIOD', 'ready_for_generation', [], [['identifier' => 'DOCTOR-B', 'name' => 'B', 'required_hours' => '1', 'standard_daily_hours' => '8'], ['identifier' => 'DOCTOR-A', 'name' => 'A', 'required_hours' => '1', 'standard_daily_hours' => '8'], ['identifier' => 'DOCTOR-C', 'name' => 'C', 'required_hours' => '1', 'standard_daily_hours' => '8']], [], 0, 'sha256:input');
    $feasibility = new RosterGenerationFeasibility('PERIOD', 1, '0.01', '0.01', '0.00', '0.01', 'staffing_demand_exceeds_targets', '0.01', '0.00', 3, [], 'One cent must reconcile.');

    $result = (new AllocateStructuralVariance)->handle($input, $feasibility, allocationPolicy('proportional_to_target_hours'));

    expect($result->allocations)->toBe(['DOCTOR-A' => '0.01', 'DOCTOR-B' => '0.00', 'DOCTOR-C' => '0.00'])
        ->and(array_sum(array_map('floatval', $result->allocations)))->toBe(0.01);
});

test('unresolved structural allocation does not invent individual fairness', function () {
    $result = (new AllocateStructuralVariance)->handle(heterogeneousInput(), excessFeasibility(), allocationPolicy('unresolved'));

    expect($result->status)->toBe('unresolved_policy')->and($result->allocations)->toBe([])->and($result->isResolved())->toBeFalse();
});
