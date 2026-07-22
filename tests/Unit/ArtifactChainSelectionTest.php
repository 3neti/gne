<?php

use App\Domain\Compilation\AmbiguousArtifactSelection;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\CrossSubjectReferenceViolation;
use App\Domain\Compilation\SelectArtifactChain;
use App\Domain\Repository\CanonicalRepositoryFingerprint;
use App\Domain\Repository\DiscoverRepository;
use App\Domain\Repository\RepositoryManifest;
use Illuminate\Filesystem\Filesystem;

it('selects deterministic latest accepted revisions within one subject', function () {
    $files = new Filesystem;
    $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle(dirname(__DIR__, 2));

    $chain = (new SelectArtifactChain)->handle($manifest, new CompilationSubject('RESERVATION-000001', 'PropertyReservation'), 'PROFILE-PROPERTY-RESERVATION', 'SCENARIO-MANUAL-PAYMENT-RESERVATION');

    expect($chain->one('Invoice')['revision'])->toBe(2)
        ->and(collect($chain->artifacts)->pluck('subject.identifier')->unique()->all())->toBe(['RESERVATION-000001'])
        ->and($chain->artifacts)->toBe((new SelectArtifactChain)->handle($manifest, $chain->subject, $chain->profile, $chain->scenario)->artifacts);
});

it('rejects references across compilation subjects', function () {
    $files = new Filesystem;
    $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle(dirname(__DIR__, 2));
    $invoice = collect($manifest->artifacts)->firstWhere('identifier', 'ARTIFACT-INVOICE-000001');
    $invoice['revision'] = 3;
    $invoice['status'] = 'accepted';
    $invoice['references'] = [['identifier' => 'ARTIFACT-APPLICATION-000002', 'revision' => 1]];
    $changed = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, [...$manifest->artifacts, $invoice]);

    (new SelectArtifactChain)->handle($changed, new CompilationSubject('RESERVATION-000001', 'PropertyReservation'), 'PROFILE-PROPERTY-RESERVATION', 'SCENARIO-MANUAL-PAYMENT-RESERVATION');
})->throws(CrossSubjectReferenceViolation::class);

it('fails rather than choosing an ambiguous artifact identity', function () {
    $files = new Filesystem;
    $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle(dirname(__DIR__, 2));
    $invoice = collect($manifest->artifacts)->firstWhere('identifier', 'ARTIFACT-INVOICE-000001');
    $invoice['identifier'] = 'ARTIFACT-INVOICE-ALTERNATE';
    $invoice['revision'] = 1;
    $invoice['status'] = 'accepted';
    $changed = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, [...$manifest->artifacts, $invoice]);
    $chain = (new SelectArtifactChain)->handle($changed, new CompilationSubject('RESERVATION-000001', 'PropertyReservation'), 'PROFILE-PROPERTY-RESERVATION', 'SCENARIO-MANUAL-PAYMENT-RESERVATION');

    $chain->one('Invoice');
})->throws(AmbiguousArtifactSelection::class);
