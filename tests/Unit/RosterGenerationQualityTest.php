<?php

use App\Application\Rostering\AnalyzeDraftRosterQuality;
use App\Application\Rostering\AnalyzeRosterGenerationFeasibility;
use App\Domain\Rostering\GeneratedRosterResult;
use App\Domain\Rostering\RosterGenerationInput;

function qualityInput(array $days, array $doctors, array $availability = []): RosterGenerationInput
{
    return new RosterGenerationInput('ROSTER-QUALITY', 'ready_for_generation', $days, $doctors, $availability, 0, 'sha256:input');
}

it('classifies aggregate staffing feasibility from direct normalized inputs', function () {
    $analyzer = new AnalyzeRosterGenerationFeasibility;
    $doctors = [
        ['identifier' => 'DOCTOR-A', 'name' => 'Dr. Ana', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'],
        ['identifier' => 'DOCTOR-B', 'name' => 'Dr. Ben', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'],
    ];

    $excess = $analyzer->handle(qualityInput([['date' => '2026-09-01', 'required' => 3]], $doctors));
    $exact = $analyzer->handle(qualityInput([['date' => '2026-09-01', 'required' => 2]], $doctors));
    $deficit = $analyzer->handle(qualityInput([['date' => '2026-09-01', 'required' => 1]], $doctors));

    expect($excess->totalRequiredStaffingHours)->toBe('24.00')
        ->and($excess->totalDoctorTargetHours)->toBe('16.00')
        ->and($excess->structuralHoursVariance)->toBe('8.00')
        ->and($excess->classification)->toBe('targets_below_staffing_demand')
        ->and($exact->classification)->toBe('targets_exactly_match_demand')
        ->and($deficit->classification)->toBe('targets_above_staffing_demand')
        ->and($deficit->minimumUnavoidableDeficit)->toBe('8.00');
});

it('keeps unrelated period data outside feasibility identity', function () {
    $input = qualityInput(
        [['date' => '2026-09-01', 'required' => 1]],
        [['identifier' => 'DOCTOR-A', 'name' => 'Dr. Ana', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00']],
    );

    $first = (new AnalyzeRosterGenerationFeasibility)->handle($input);
    $second = (new AnalyzeRosterGenerationFeasibility)->handle($input);

    expect($first->toArray())->toBe($second->toArray());
});

it('separates equal structural excess from residual doctor imbalance', function () {
    $input = qualityInput(
        [['date' => '2026-09-01', 'required' => 3]],
        [
            ['identifier' => 'DOCTOR-A', 'name' => 'Dr. Ana', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'],
            ['identifier' => 'DOCTOR-B', 'name' => 'Dr. Ben', 'standard_daily_hours' => '8.00', 'required_hours' => '8.00'],
        ],
    );
    $result = new GeneratedRosterResult(
        [
            ['doctor_identifier' => 'DOCTOR-A', 'doctor_name' => 'Dr. Ana', 'date' => '2026-09-01', 'credited_hours' => '8.00'],
            ['doctor_identifier' => 'DOCTOR-A', 'doctor_name' => 'Dr. Ana', 'date' => '2026-09-02', 'credited_hours' => '8.00'],
            ['doctor_identifier' => 'DOCTOR-B', 'doctor_name' => 'Dr. Ben', 'date' => '2026-09-01', 'credited_hours' => '8.00'],
        ],
        [['date' => '2026-09-01', 'required' => 3, 'assigned' => 3, 'status' => 'fully_staffed']],
        [
            ['doctor_identifier' => 'DOCTOR-A', 'required_hours' => '8.00', 'assigned_hours' => '16.00', 'variance' => '8.00', 'assignment_count' => 2, 'status' => 'above_target'],
            ['doctor_identifier' => 'DOCTOR-B', 'required_hours' => '8.00', 'assigned_hours' => '8.00', 'variance' => '0.00', 'assignment_count' => 1, 'status' => 'on_target'],
        ],
        [],
        [],
        'valid',
        'sha256:generation',
    );
    $feasibility = (new AnalyzeRosterGenerationFeasibility)->handle($input);

    $quality = (new AnalyzeDraftRosterQuality)->handle($input, $result, $feasibility);

    expect($quality->doctorHours[0]['raw_variance'])->toBe('8.00')
        ->and($quality->doctorHours[0]['allocated_structural_variance'])->toBe('4.00')
        ->and($quality->doctorHours[0]['residual_variance'])->toBe('4.00')
        ->and($quality->doctorHours[1]['residual_variance'])->toBe('-4.00')
        ->and($quality->metrics['raw_variance_range'])->toBe('8.00')
        ->and($quality->metrics['residual_variance_range'])->toBe('8.00')
        ->and($quality->classification)->toBe('acceptable_with_warnings');
});
