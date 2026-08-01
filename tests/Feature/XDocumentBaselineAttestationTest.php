<?php

use App\Integration\XDocument\XDocumentPackageBaselineAttestor;
use App\Integration\XDocument\XDocumentRuntimeDiagnostics;
use Illuminate\Support\Facades\Artisan;

it('attests the exact reviewed package sources and reports evidence-based diagnostics', function () {
    $attestations = app(XDocumentPackageBaselineAttestor::class)->assertMatches();
    $diagnostics = app(XDocumentRuntimeDiagnostics::class)->toArray();

    expect($attestations['x_document']->actualCommit)->toBe(XDocumentPackageBaselineAttestor::XDocumentCommit)
        ->and($attestations['x_document_laravel']->actualCommit)->toBe(XDocumentPackageBaselineAttestor::XDocumentLaravelCommit)
        ->and($diagnostics)->toMatchArray([
            'x_document_expected_commit' => XDocumentPackageBaselineAttestor::XDocumentCommit,
            'x_document_actual_commit' => XDocumentPackageBaselineAttestor::XDocumentCommit,
            'x_document_baseline_matches' => true,
            'x_document_laravel_expected_commit' => XDocumentPackageBaselineAttestor::XDocumentLaravelCommit,
            'x_document_laravel_actual_commit' => XDocumentPackageBaselineAttestor::XDocumentLaravelCommit,
            'x_document_laravel_baseline_matches' => true,
            'contract_validator_available' => true,
            'contract_smoke_passed' => true,
            'browser_composition_available' => true,
            'http_factory_bound' => true,
            'authenticated_http_route_available' => true,
        ]);
});

it('passes the deployment-oriented MVP smoke command', function () {
    expect(Artisan::call('gne:mvp:smoke', ['--json' => true]))->toBe(0);
    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($result['passed'])->toBeTrue()
        ->and($result['repository_valid'])->toBeTrue()
        ->and($result['package_baselines']['x_document']['matches'])->toBeTrue()
        ->and($result['package_baselines']['x_document_laravel']['matches'])->toBeTrue()
        ->and($result['contract_smoke']['passed'])->toBeTrue()
        ->and($result['http_factory_bound'])->toBeTrue()
        ->and($result['authenticated_route_available'])->toBeTrue()
        ->and($result['application_url'])->toBe((string) config('app.url'))
        ->and($result['browser_route_path'])->toBe('/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser')
        ->and($result['browser_route_url'])->toStartWith((string) config('app.url'));
});
