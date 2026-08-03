<?php

use App\Application\Rostering\RunDraftRosterGenerationScenario;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;

it('proves deterministic preview one batch revision and a later manual correction', function () {
    $definition = RosterLifecycleScenarioDefinition::fromFile(base_path('business/profiles/anaesthesia-rostering/scenarios/draft-roster-generation.yaml'));
    $report = app(RunDraftRosterGenerationScenario::class)->handle($definition)->toArray();
    $generatedCalendarAssignments = collect($report['generation_state']['calendar'])->flatMap(fn (array $day): array => $day['assigned_doctors']);
    $generatedResultCells = collect($report['generation']['assignments'])->map(fn (array $assignment): string => $assignment['doctor_identifier'].'|'.$assignment['date'])->sort()->values();
    $generatedCalendarCells = collect($report['generation_state']['calendar'])->flatMap(fn (array $day): array => array_map(fn (array $doctor): string => $doctor['doctor_identifier'].'|'.$day['date'], $day['assigned_doctors']))->sort()->values();
    $matrixCells = collect($report['current_state']['doctor_matrix'])->sum(fn (array $doctor): int => count($doctor['dates']));

    expect($report['preview']['deterministic'])->toBeTrue()
        ->and($report['preview']['state_unchanged'])->toBeTrue()
        ->and($report['generation']['summary'])->toMatchArray(['assignments_created' => 180, 'dates_evaluated' => 28, 'required_slots' => 180, 'fully_staffed_dates' => 28, 'understaffed_dates' => 0, 'overstaffed_dates' => 0, 'errors' => 0])
        ->and($report['batch'])->toMatchArray(['generation_runs' => 1, 'revision_changes' => 180, 'generation_audits' => 1])
        ->and($report['generated_validation']['status'])->toBe('valid_with_warnings')
        ->and($report['generation_state']['assignments'])->toHaveCount(180)
        ->and($generatedCalendarAssignments)->toHaveCount(180)
        ->and($generatedCalendarAssignments->pluck('assignment_identifier')->unique())->toHaveCount(180)
        ->and($generatedCalendarCells->all())->toBe($generatedResultCells->all())
        ->and($report['current_state']['assignments'])->toHaveCount(180)
        ->and($report['current_state']['doctor_matrix'])->toHaveCount(10)
        ->and($matrixCells)->toBe(280)
        ->and(collect($report['generation_state']['calendar'])->where('staffing_status', 'fully_staffed'))->toHaveCount(28)
        ->and(collect($report['current_state']['calendar'])->where('staffing_status', 'fully_staffed'))->toHaveCount(26)
        ->and(collect($report['current_state']['calendar'])->where('staffing_status', 'understaffed'))->toHaveCount(1)
        ->and(collect($report['current_state']['calendar'])->where('staffing_status', 'overstaffed'))->toHaveCount(1)
        ->and(collect($report['current_state']['assignments'])->where('source', 'manually_changed'))->toHaveCount(1)
        ->and($report['manual_correction']['revision'])->toBe(2)
        ->and($report['manual_correction']['source'])->toBe('manually_changed')
        ->and($report['state_preserved'])->toBeFalse();
});

it('generates complete disposable JSON HTML and PDF evidence', function () {
    if (getenv('GNE_PDF_TESTS') !== '1') {
        $this->markTestSkipped('Set GNE_PDF_TESTS=1 where Chromium process execution is available.');
    }
    $output = base_path('.gne/reports/rostering/draft-generation-test');
    $this->artisan('gne:roster:lifecycle:run', ['--scenario' => 'ANAESTHESIA-DRAFT-ROSTER-GENERATION', '--artifact' => true, '--output' => $output])->assertSuccessful();

    expect($output.'/report.json')->toBeFile()
        ->and($output.'/html/index.html')->toBeFile()
        ->and($output.'/html/generated-calendar.html')->toBeFile()
        ->and($output.'/html/current-roster-calendar.html')->toBeFile()
        ->and($output.'/html/assignments-by-date.html')->toBeFile()
        ->and($output.'/html/doctor-matrix-week-1.html')->toBeFile()
        ->and($output.'/html/doctor-matrix-week-4.html')->toBeFile()
        ->and($output.'/html/preferences.html')->toBeFile()
        ->and($output.'/html/explanations.html')->toBeFile()
        ->and($output.'/pdf/anaesthesia-generated-draft-roster.pdf')->toBeFile()
        ->and(file_get_contents($output.'/html/index.html'))->toContain('Automatically Generated Draft', 'not mathematically optimal')
        ->and(file_get_contents($output.'/html/generated-calendar.html'))->toContain('Dr. Ana Reyes', 'Dr. Jules Co', '2026-09-01', '2026-09-28', 'generated', 'FULL')
        ->and(file_get_contents($output.'/html/current-roster-calendar.html'))->toContain('manually_changed', 'r2', 'UNDER', 'OVER')
        ->and(file_get_contents($output.'/html/assignments-by-date.html'))->toContain('all 180 generated assignments', 'Dr. Ana Reyes', 'Dr. Jules Co')
        ->and(file_get_contents($output.'/html/doctor-matrix-week-1.html'))->toContain('G', 'M r2', 'U', 'PW', 'PO!', 'Dr. Ana Reyes')
        ->and(file_get_contents($output.'/html/doctor-matrix-week-2.html'))->toContain('L', 'Dr. Jules Co')
        ->and(file_get_contents($output.'/html/explanations.html'))->toContain('ACTIVE_DOCTOR', 'STABLE_TIE_BREAK')
        ->and(substr_count(file_get_contents($output.'/html/generated-calendar.html'), 'generated - r1'))->toBe(180)
        ->and(file_get_contents($output.'/html/print.html'))->toContain('Original Generated Roster - Week 1', 'Current Roster After Manual Correction - Week 1', 'Current Assignments by Date - Week 1', 'Doctor-by-date Matrix - Week 1', 'Doctor-by-date Matrix - Week 2', 'Doctor-by-date Matrix - Week 3', 'Doctor-by-date Matrix - Week 4', 'Dr. Ana Reyes', 'manually_changed', 'M r2')
        ->and(file_get_contents($output.'/html/print.html'))->not->toContain('http://', 'https://')
        ->and(file_get_contents($output.'/report.json'))->not->toContain(base_path(), '/Users/');
});
