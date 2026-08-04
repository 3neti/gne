<?php

use Illuminate\Filesystem\Filesystem;

it('keeps prohibited optimization import and integration machinery out of rostering', function () {
    $files = new Filesystem;
    $root = dirname(__DIR__, 2);
    $roots = ["{$root}/app/Application/Rostering", "{$root}/app/Domain/Rostering", "{$root}/resources/js/pages/rostering", "{$root}/business/profiles/anaesthesia-rostering"];
    $controllerFiles = $files->glob("{$root}/app/Http/Controllers/*Roster*.php");
    $source = collect($roots)->flatMap(fn (string $root) => $files->allFiles($root))->map(fn ($file): string => $file->getContents())->merge(array_map(fn (string $path): string => $files->get($path), $controllerFiles))->implode("\n");

    expect($source)->not->toContain('OR-Tools')
        ->not->toContain('x-optimization')
        ->not->toContain('LBHurtado\\XChange')
        ->not->toContain('LBHurtado\\XDocument')
        ->not->toContain('PhpSpreadsheet')
        ->not->toContain('on_call')
        ->not->toContain('second_call');
});

it('keeps active assignment duty vocabulary deliberately small', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Domain/Rostering/DutyCode.php'))->toContain('StandardDay')
        ->not->toContain('Leave', 'Unavailable')
        ->not->toContain('OnCall')
        ->not->toContain('Overtime');
});

it('keeps request resolution in the application layer and generation out of the request slice', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/DoctorScheduleRequestController.php');
    $availabilityPage = file_get_contents($root.'/resources/js/pages/rostering/periods/Availability.vue');
    $scenario = file_get_contents($root.'/app/Domain/Rostering/RosterLifecycleScenarioDefinition.php');

    expect($controller)->toContain('ValidateDoctorRequests')
        ->not->toContain('conflict_codes', 'effective_status', 'RosterGenerator')
        ->and($availabilityPage)->not->toContain('leave > unavailable', 'preferred_work && preferred_off', 'fetch(')
        ->and($scenario)->toContain("'record_requests'", "'prove_hard_conflict'", "'resolve_hard_conflict'")
        ->not->toContain('class_exists($step');
});

it('keeps availability semantics out of the Vue calendar', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/rostering/periods/Availability.vue');

    expect($source)->toContain('Eligibility is not an assignment', 'Unspecified', 'staffing_input_status')
        ->not->toContain('eligible_doctor_count >=', "effective_status === 'unspecified'", 'RosterGenerator');
});

it('keeps manual roster rules in application services and generation absent', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/RosterAssignmentController.php');
    $page = file_get_contents($root.'/resources/js/pages/rostering/periods/Assignments.vue');
    $preview = file_get_contents($root.'/app/Application/Rostering/PreviewRosterMutation.php');
    $audit = file_get_contents($root.'/app/Contracts/Rostering/RosterAuditRecorder.php');

    expect($controller)->toContain('CreateRosterAssignment', 'RemoveRosterAssignment', 'MoveRosterAssignment', 'ReplaceRosterAssignment', 'PreviewRosterMutation')
        ->not->toContain('RosterAssignment::query()->create', "->update(['status'")
        ->and($page)->toContain('calendar', 'doctor_hours', 'validation', 'revisions')->not->toContain('fetch(', 'assigned_count >= required_count')
        ->and($preview)->toContain('DB::beginTransaction()', 'DB::rollBack()')->not->toContain('RosterAuditRecorder', 'CreateRosterRevision')
        ->and($audit)->toContain('interface RosterAuditRecorder', 'public function record')
        ->and($controller.$page.$preview)->not->toContain('RosterGenerator', 'OR-Tools', 'PhpSpreadsheet', 'published');
});

it('owns all roster-period audit reads through the exact-period query service', function () {
    $root = dirname(__DIR__, 2);
    $assignmentController = file_get_contents($root.'/app/Http/Controllers/RosterAssignmentController.php');
    $historyController = file_get_contents($root.'/app/Http/Controllers/RosterHistoryController.php');
    $manualScenario = file_get_contents($root.'/app/Application/Rostering/RunManualRosterScenario.php');
    $queryService = file_get_contents($root.'/app/Application/Rostering/ListRosterPeriodAuditEntries.php');

    expect($assignmentController.$historyController.$manualScenario)->toContain('ListRosterPeriodAuditEntries')
        ->not->toContain('RosterAuditEntry::query()')
        ->and($queryService)->toContain('whereBelongsTo($rosterPeriod)', "orderBy('created_at')", "orderBy('id')")
        ->not->toContain('new_value', 'entity_identifier', 'reason');
});

it('keeps generated-draft quality read-only and outside presentation', function () {
    $root = dirname(__DIR__, 2);
    $decisionRegister = file_get_contents($root.'/DECISION_REGISTER.md');
    $feasibility = file_get_contents($root.'/app/Application/Rostering/AnalyzeRosterGenerationFeasibility.php');
    $quality = file_get_contents($root.'/app/Application/Rostering/AnalyzeDraftRosterQuality.php');
    $renderer = file_get_contents($root.'/app/Infrastructure/Rostering/RenderDraftGenerationReport.php');

    expect($decisionRegister)->toContain('one top-level roster revision', 'many revision changes', 'Structural variance and residual allocation imbalance are distinct')
        ->and($feasibility)->toContain('RosterGenerationInput')->not->toContain('RosterPeriod', 'Doctor::', 'DB::')
        ->and($quality)->toContain('GeneratedRosterResult', 'DraftRosterQualityResult')->not->toContain('save(', 'update(', 'create(', 'DB::')
        ->and($renderer)->toContain("['generation']['quality']", "['generation']['feasibility']")
        ->not->toContain('raw_variance =', 'structuralHoursVariance /')
        ->and($feasibility.$quality.$renderer)->not->toContain('random_int', 'mt_rand', 'OR-Tools', 'OptimizeRoster', 'published');
});
