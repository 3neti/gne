<?php

use App\Application\Rostering\RunManualRosterScenario;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Models\RosterAssignment;
use App\Models\RosterAuditEntry;
use App\Models\RosterRevision;
use Illuminate\Support\Facades\File;

it('loads and runs the deterministic manual roster scenario with rollback by default', function () {
    $definition = RosterLifecycleScenarioDefinition::fromFile(base_path('business/profiles/anaesthesia-rostering/scenarios/manual-roster.yaml'));
    $before = [RosterAssignment::query()->count(), RosterRevision::query()->count(), RosterAuditEntry::query()->count()];

    $report = app(RunManualRosterScenario::class)->handle($definition)->toArray();

    expect($definition->identifier)->toBe('ANAESTHESIA-MANUAL-ROSTER')
        ->and($report['scenario']['passed'])->toBeTrue()
        ->and($report['roster']['status'])->toBe('generated')
        ->and($report['roster']['validation_status'])->toBe('valid_with_warnings')
        ->and($report['calendar'])->toHaveCount(28)
        ->and($report['doctor_hours'])->toHaveCount(10)
        ->and($report['assignments'])->toHaveCount(181)
        ->and($report['revisions'])->toHaveCount(186)
        ->and($report['proofs']['leave_rejected']['rejected'])->toBeTrue()
        ->and($report['proofs']['unavailable_rejected']['rejected'])->toBeTrue()
        ->and($report['proofs']['duplicate_rejected']['state_unchanged'])->toBeTrue()
        ->and($report['proofs']['dry_run']['state_unchanged'])->toBeTrue()
        ->and($report['proofs']['remove']['understaffing_visible'])->toBeTrue()
        ->and(collect($report['calendar'])->where('staffing_status', 'fully_staffed'))->toHaveCount(27)
        ->and(collect($report['calendar'])->where('staffing_status', 'overstaffed'))->toHaveCount(1)
        ->and(collect($report['validation']['findings'])->pluck('code'))->toContain('ASSIGNMENT_PREFERRED_OFF')
        ->and([RosterAssignment::query()->count(), RosterRevision::query()->count(), RosterAuditEntry::query()->count()])->toBe($before);
});

it('renders finalized self-contained manual roster artifacts that survive rollback', function () {
    if (! getenv('GNE_PDF_TESTS')) {
        $this->markTestSkipped('Set GNE_PDF_TESTS=1 where Chromium process execution is available.');
    }
    $output = storage_path('framework/testing/manual-roster-artifact');
    File::deleteDirectory($output);

    $this->artisan('gne:roster:lifecycle:run', ['--scenario' => 'ANAESTHESIA-MANUAL-ROSTER', '--artifact' => true, '--output' => $output, '--json' => true])->assertSuccessful();

    expect($output.'/report.json')->toBeFile()
        ->and($output.'/html/index.html')->toBeFile()
        ->and($output.'/html/roster-calendar.html')->toBeFile()
        ->and($output.'/html/doctor-matrix.html')->toBeFile()
        ->and($output.'/html/doctor-hours.html')->toBeFile()
        ->and($output.'/html/staffing.html')->toBeFile()
        ->and($output.'/html/validation.html')->toBeFile()
        ->and($output.'/html/revisions.html')->toBeFile()
        ->and($output.'/pdf/anaesthesia-manual-roster.pdf')->toBeFile()
        ->and(File::get($output.'/html/roster-calendar.html'))->toContain('2026-09-01', '2026-09-28', 'Dr. Ana Reyes')
        ->and(File::get($output.'/html/print.html'))->toContain('Anaesthesia Department Draft Roster', 'Manually Authored Draft — Not Yet Published')->not->toContain('No roster assignments have been generated', base_path(), '/Users/')
        ->and(filesize($output.'/pdf/anaesthesia-manual-roster.pdf'))->toBeGreaterThan(1000)
        ->and(RosterAssignment::query()->count())->toBe(0);
});
