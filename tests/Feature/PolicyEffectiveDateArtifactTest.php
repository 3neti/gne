<?php

use App\Application\Rostering\RunPolicyCalibrationScenario;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Infrastructure\Rostering\RenderPolicyCalibrationReport;
use Illuminate\Filesystem\Filesystem;

test('policy scenario and decision artifact expose temporal and closed-choice proofs', function () {
    $definition = RosterLifecycleScenarioDefinition::fromFile(base_path('business/profiles/anaesthesia-rostering/scenarios/generation-policy-calibration.yaml'));
    $report = app(RunPolicyCalibrationScenario::class)->handle($definition)->toArray();
    $output = storage_path('framework/testing/policy-effective-date-artifact');
    app(RenderPolicyCalibrationReport::class)->handle($output, $report);
    $print = file_get_contents($output.'/html/print.html');

    expect($report['steps'])->toHaveCount(12)
        ->and($report['temporal_resolution'])->toMatchArray(['pending_department_decisions' => 8, 'future_revisions_excluded_from_current_fingerprint' => true])
        ->and($report['impact_previews']['explicit_availability_required']['mutation'])->toBeFalse()
        ->and($print)->toContain('Registered candidate choices', 'Eligible unless blocked', 'Explicit availability required', 'Proportional to target hours', '179 of 180', 'Pending confirmation', 'Decision authority name', 'Effective date', 'Signature / acknowledgment')
        ->and($print)->not->toContain('anaesthesia_department', base_path(), '/Users/');

    app(Filesystem::class)->deleteDirectory($output);
});
