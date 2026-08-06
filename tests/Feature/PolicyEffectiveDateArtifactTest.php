<?php

use App\Application\Rostering\RunPolicyCalibrationScenario;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Infrastructure\Rostering\RenderPolicyCalibrationReport;
use Illuminate\Filesystem\Filesystem;

test('policy scenario and decision artifact expose temporal and closed-choice proofs', function () {
    $definition = RosterLifecycleScenarioDefinition::fromFile(base_path('business/profiles/anaesthesia-rostering/scenarios/generation-policy-calibration.yaml'));
    $report = app(RunPolicyCalibrationScenario::class)->handle($definition)->toArray();
    $output = storage_path('framework/testing/policy-effective-date-artifact');
    $rendered = app(RenderPolicyCalibrationReport::class)->handle($output, $report);
    $print = file_get_contents($output.'/html/print.html');

    expect($report['steps'])->toHaveCount(14)
        ->and($rendered['pages'])->toBe(17)
        ->and($report['confirmability'])->toMatchArray(['unsupported_options' => 2, 'discovery_only_topics' => ['public_holidays', 'variable_credited_hours']])
        ->and($report['confirmability']['proofs']['weekend_without_configuration']['confirmability'])->toBe('configuration_required')
        ->and($report['confirmability']['proofs']['weekend_configured']['confirmability'])->toBe('confirmable')
        ->and($report['temporal_resolution'])->toMatchArray(['pending_department_decisions' => 8, 'future_revisions_excluded_from_current_fingerprint' => true])
        ->and($report['impact_previews']['explicit_availability_required']['mutation'])->toBeFalse()
        ->and($report['compatibility_matrix'])->toMatchArray(['compatible_count' => 9, 'incompatible_count' => 3])
        ->and($report['enforcement_readiness']['status'])->toBe('ready_for_enforcement')
        ->and($report['coherence_proofs'])->toMatchArray(['contradictory_combination' => 'policy_conflict', 'holiday_metadata_excluded_from_enforcement_fingerprint' => true, 'employment_scope' => 'availability_eligibility_only', 'candidate_preview_mutation' => false, 'rollback_default' => true])
        ->and($print)->toContain('Registered candidate choices', 'Eligible unless blocked', 'Explicit availability required', 'Proportional to target hours', 'Maximum allowed difference', 'Maximum consecutive days', 'Categories requiring explicit availability', 'Not supported in this release', 'Discovery topic only', 'Permanent Supersession', 'does not resume', 'Decision pending', '179 of 180', 'Pending confirmation', 'Decision authority name', 'Effective date', 'Signature / acknowledgment', 'Required Hours Compatibility', 'Enforcement Readiness', 'Employment-Type Availability Eligibility')
        ->and($print)->not->toContain('Leave unresolved')
        ->and($print)->not->toContain('anaesthesia_department', base_path(), '/Users/');

    app(Filesystem::class)->deleteDirectory($output);
});
