<?php

use App\Application\Rostering\RunDraftRosterGenerationScenario;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;

it('proves deterministic preview one batch revision and a later manual correction', function () {
    $definition = RosterLifecycleScenarioDefinition::fromFile(base_path('business/profiles/anaesthesia-rostering/scenarios/draft-roster-generation.yaml'));
    $report = app(RunDraftRosterGenerationScenario::class)->handle($definition)->toArray();

    expect($report['preview']['deterministic'])->toBeTrue()
        ->and($report['preview']['state_unchanged'])->toBeTrue()
        ->and($report['generation']['summary'])->toMatchArray(['assignments_created' => 180, 'dates_evaluated' => 28, 'required_slots' => 180, 'fully_staffed_dates' => 28, 'understaffed_dates' => 0, 'overstaffed_dates' => 0, 'errors' => 0])
        ->and($report['batch'])->toMatchArray(['generation_runs' => 1, 'revision_changes' => 180, 'generation_audits' => 1])
        ->and($report['generated_validation']['status'])->toBe('valid_with_warnings')
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
        ->and($output.'/html/preferences.html')->toBeFile()
        ->and($output.'/html/explanations.html')->toBeFile()
        ->and($output.'/pdf/anaesthesia-generated-draft-roster.pdf')->toBeFile()
        ->and(file_get_contents($output.'/html/index.html'))->toContain('Automatically Generated Draft', 'not mathematically optimal')
        ->and(file_get_contents($output.'/html/explanations.html'))->toContain('ACTIVE_DOCTOR', 'STABLE_TIE_BREAK')
        ->and(file_get_contents($output.'/report.json'))->not->toContain(base_path(), '/Users/');
});
