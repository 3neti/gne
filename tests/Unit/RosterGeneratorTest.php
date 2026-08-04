<?php

use App\Application\Rostering\BalancedGreedyRosterGenerator;
use App\Contracts\Rostering\RosterGenerator;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationInput;

it('generates deterministic balanced proposals without persistence', function () {
    $input = new RosterGenerationInput('ROSTER-A', 'ready_for_generation', [['date' => '2026-09-01', 'required' => 1], ['date' => '2026-09-02', 'required' => 1]], [['identifier' => 'DOCTOR-A', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'], ['identifier' => 'DOCTOR-B', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00']], [['doctor_identifier' => 'DOCTOR-A', 'date' => '2026-09-01', 'effective_status' => 'available', 'explicit_availability' => true, 'preference' => 'none'], ['doctor_identifier' => 'DOCTOR-B', 'date' => '2026-09-01', 'effective_status' => 'unspecified', 'explicit_availability' => false, 'preference' => 'none'], ['doctor_identifier' => 'DOCTOR-A', 'date' => '2026-09-02', 'effective_status' => 'unspecified', 'explicit_availability' => false, 'preference' => 'preferred_off'], ['doctor_identifier' => 'DOCTOR-B', 'date' => '2026-09-02', 'effective_status' => 'unspecified', 'explicit_availability' => false, 'preference' => 'preferred_work']], 0, 'sha256:input');
    $policy = new ResolvedRosterPolicy('PROFILE-ANAESTHESIA-ROSTERING', 1, 'balanced_greedy', '1.0', [], 'sha256:policy');
    $generator = new BalancedGreedyRosterGenerator;

    $first = $generator->generate($input, $policy);
    $second = $generator->generate($input, $policy);

    expect($generator)->toBeInstanceOf(RosterGenerator::class)
        ->and($first->assignments)->toBe($second->assignments)
        ->and($first->fingerprint)->toBe($second->fingerprint)
        ->and(array_column($first->assignments, 'doctor_identifier'))->toBe(['DOCTOR-A', 'DOCTOR-B'])
        ->and($first->assignments[1]['explanation']['reason_codes'])->toContain('PREFERRED_WORK')
        ->and($first->assignments[1]['explanation']['ranked_candidates'][0]['doctor_identifier'])->toBe('DOCTOR-B')
        ->and($first->assignments[1]['explanation']['ranked_candidates'][1]['doctor_identifier'])->toBe('DOCTOR-A')
        ->and($first->assignments[1]['explanation']['selected_over']['criteria']['preferred_off'])->toBeTrue()
        ->and($first->assignments[0]['explanation']['reason_codes'])->not->toContain('STABLE_TIE_BREAK')
        ->and(collect($first->preferences)->firstWhere('type', 'preferred_work')['explanation'])->toContain('elevated')
        ->and(collect($first->preferences)->firstWhere('type', 'preferred_off')['explanation'])->toContain('preferred-off penalty')
        ->and(json_encode($first->toArray(), JSON_THROW_ON_ERROR))->not->toContain('fatigue', 'specialty', 'seniority');
});

it('reports a stable tie break only when preceding ranking facts are equal', function () {
    $input = new RosterGenerationInput('ROSTER-A', 'ready_for_generation', [['date' => '2026-09-01', 'required' => 1]], [['identifier' => 'DOCTOR-A', 'name' => 'Dr. Ana', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'], ['identifier' => 'DOCTOR-B', 'name' => 'Dr. Ben', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00']], [['doctor_identifier' => 'DOCTOR-A', 'date' => '2026-09-01', 'effective_status' => 'unspecified', 'explicit_availability' => false, 'preference' => 'none'], ['doctor_identifier' => 'DOCTOR-B', 'date' => '2026-09-01', 'effective_status' => 'unspecified', 'explicit_availability' => false, 'preference' => 'none']], 0, 'sha256:input');

    $result = (new BalancedGreedyRosterGenerator)->generate($input, new ResolvedRosterPolicy('PROFILE', 1, 'balanced_greedy', '1.0', [], 'sha256:policy'));

    expect($result->assignments[0]['doctor_identifier'])->toBe('DOCTOR-A')
        ->and($result->assignments[0]['explanation']['reason_codes'])->toContain('STABLE_TIE_BREAK')
        ->and($result->assignments[0]['explanation']['selected_over']['doctor_identifier'])->toBe('DOCTOR-B');
});

it('never selects leave or unavailable doctors and reports shortages', function () {
    $input = new RosterGenerationInput('ROSTER-A', 'ready_for_generation', [['date' => '2026-09-01', 'required' => 2]], [['identifier' => 'DOCTOR-A', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'], ['identifier' => 'DOCTOR-B', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00']], [['doctor_identifier' => 'DOCTOR-A', 'date' => '2026-09-01', 'effective_status' => 'leave', 'explicit_availability' => false, 'preference' => 'none'], ['doctor_identifier' => 'DOCTOR-B', 'date' => '2026-09-01', 'effective_status' => 'unavailable', 'explicit_availability' => false, 'preference' => 'none']], 0, 'sha256:input');
    $result = (new BalancedGreedyRosterGenerator)->generate($input, new ResolvedRosterPolicy('PROFILE', 1, 'balanced_greedy', '1.0', [], 'sha256:policy'));

    expect($result->assignments)->toBeEmpty()
        ->and($result->dailyStaffing[0]['missing_slots'])->toBe(2)
        ->and($result->hasErrors())->toBeTrue();
});
