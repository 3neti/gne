<?php

use App\Domain\Compilation\AmbiguousArtifactSelection;
use App\Domain\Compilation\BuildResolvedDocumentSet;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\CrossSubjectReferenceViolation;
use App\Domain\Compilation\DocumentReadiness;
use App\Domain\Compilation\ResolvedDocumentSet;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Compilation\SelectArtifactChain;
use App\Domain\Repository\CanonicalRepositoryFingerprint;
use App\Domain\Repository\DiscoverRepository;
use App\Domain\Repository\RepositoryManifest;
use App\Domain\Repository\RepositorySourceLoader;
use App\Domain\Repository\ValidateArtifactPayloads;
use App\Domain\Repository\ValidateDocumentDefinitions;
use App\Domain\Repository\ValidateRepository;
use Illuminate\Filesystem\Filesystem;

function buildBootstrapDocumentSet(string $subject): ResolvedDocumentSet
{
    [$root, $manifest, $builder] = documentSetTestContext();

    return $builder->handle($root, $manifest, new CompilationSubject($subject, 'PropertyReservation'));
}

/** @return array{string, RepositoryManifest, BuildResolvedDocumentSet} */
function documentSetTestContext(): array
{
    $root = dirname(__DIR__, 2);
    $files = new Filesystem;
    $loader = new RepositorySourceLoader($files);
    $discovery = new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files));
    $validator = new ValidateRepository($discovery, new ValidateArtifactPayloads($loader), new ValidateDocumentDefinitions($loader));
    $selector = new SelectArtifactChain;
    $builder = new BuildResolvedDocumentSet($selector, new ResolveDocument($selector));

    return [$root, $validator->handle($root), $builder];
}

it('builds a deterministic complete inventory for subject one', function () {
    $set = buildBootstrapDocumentSet('RESERVATION-000001');

    expect($set->count(DocumentReadiness::Resolved))->toBe(4)
        ->and($set->count(DocumentReadiness::Pending))->toBe(1)
        ->and($set->lifecyclePosition->currentStage)->toBe('reservation_certified')
        ->and($set->lifecyclePosition->nextStage)->toBeNull()
        ->and($set->lifecyclePosition->gaps)->toBe([])
        ->and(collect($set->entries)->firstWhere('definitionIdentifier', 'DOCUMENT-BROCHURE')->missingEvidence[0]->artifactType)->toBe('PropertyOffering')
        ->and($set->toArray())->toBe(buildBootstrapDocumentSet('RESERVATION-000001')->toArray());
});

it('classifies subject two pending documents and lifecycle position', function () {
    $set = buildBootstrapDocumentSet('RESERVATION-000002');
    $entries = collect($set->entries)->keyBy('definitionIdentifier');

    expect($entries['DOCUMENT-APPLICATION']->readiness)->toBe(DocumentReadiness::Resolved)
        ->and($entries['DOCUMENT-INVOICE']->readiness)->toBe(DocumentReadiness::Resolved)
        ->and($entries['DOCUMENT-RECEIPT']->readiness)->toBe(DocumentReadiness::Pending)
        ->and($entries['DOCUMENT-RECEIPT']->missingEvidence[0]->artifactType)->toBe('PaymentApproval')
        ->and($entries['DOCUMENT-RESERVATION-CERTIFICATE']->missingEvidence[0]->artifactType)->toBe('Receipt')
        ->and($set->lifecyclePosition->currentStage)->toBe('invoice_accepted')
        ->and($set->lifecyclePosition->nextStage)->toBe('payment_evidence_submitted')
        ->and($set->lifecyclePosition->gaps)->toBe([]);
});

it('changes set identity for new subject evidence but not an unrelated repository fingerprint', function () {
    [$root, $manifest, $builder] = documentSetTestContext();
    $subject = new CompilationSubject('RESERVATION-000002', 'PropertyReservation');
    $before = $builder->handle($root, $manifest, $subject);
    $evidence = collect($manifest->artifacts)->firstWhere('identifier', 'ARTIFACT-PAYMENT-EVIDENCE-000001');
    $evidence['identifier'] = 'ARTIFACT-PAYMENT-EVIDENCE-000002';
    $evidence['subject'] = $subject->toArray();
    $evidence['references'] = [['relationship' => 'payment_evidence_for', 'identifier' => 'ARTIFACT-INVOICE-000002', 'revision' => 1]];
    $changed = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, [...$manifest->artifacts, $evidence], $manifest->fingerprint, $manifest->canonicalFiles, $manifest->findings, $manifest->lifecycles);
    $unrelated = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, $manifest->artifacts, str_repeat('f', 64), $manifest->canonicalFiles, $manifest->findings, $manifest->lifecycles);

    expect($builder->handle($root, $changed, $subject)->fingerprint)->not->toBe($before->fingerprint)
        ->and($builder->handle($root, $unrelated, $subject)->fingerprint)->toBe($before->fingerprint);
});

it('keeps document sets isolated by compilation subject', function () {
    $first = json_encode(buildBootstrapDocumentSet('RESERVATION-000001')->toArray(), JSON_THROW_ON_ERROR);
    $second = json_encode(buildBootstrapDocumentSet('RESERVATION-000002')->toArray(), JSON_THROW_ON_ERROR);

    expect($first)->not->toContain('ARTIFACT-APPLICATION-000002', 'ARTIFACT-INVOICE-000002', 'Ben Example', '75000')
        ->and($second)->not->toContain('ARTIFACT-APPLICATION-000001', 'ARTIFACT-INVOICE-000001', 'Ana Example', '50000');
});

it('reports accepted evidence beyond a missing lifecycle stage as a gap', function () {
    [$root, $manifest, $builder] = documentSetTestContext();
    $artifacts = collect($manifest->artifacts)
        ->reject(fn (array $artifact): bool => $artifact['identifier'] === 'ARTIFACT-ASSESSMENT-000002')
        ->values()->all();
    $withGap = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, $artifacts, $manifest->fingerprint, $manifest->canonicalFiles, $manifest->findings, $manifest->lifecycles);

    $set = $builder->handle($root, $withGap, new CompilationSubject('RESERVATION-000002', 'PropertyReservation'));

    expect($set->lifecyclePosition->currentStage)->toBe('application_accepted')
        ->and($set->lifecyclePosition->nextStage)->toBe('assessment_completed')
        ->and($set->lifecyclePosition->gaps)->toContain('invoice_accepted');
});

it('propagates ambiguous evidence instead of classifying it as unavailable', function () {
    [$root, $manifest, $builder] = documentSetTestContext();
    $invoice = collect($manifest->artifacts)->firstWhere('identifier', 'ARTIFACT-INVOICE-000001');
    $invoice['identifier'] = 'ARTIFACT-INVOICE-ALTERNATE';
    $invoice['revision'] = 1;
    $invoice['status'] = 'accepted';
    $ambiguous = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, [...$manifest->artifacts, $invoice], $manifest->fingerprint, $manifest->canonicalFiles, $manifest->findings, $manifest->lifecycles);

    $builder->handle($root, $ambiguous, new CompilationSubject('RESERVATION-000001', 'PropertyReservation'));
})->throws(AmbiguousArtifactSelection::class);

it('propagates cross-subject contamination instead of producing an inventory', function () {
    [$root, $manifest, $builder] = documentSetTestContext();
    $invoice = collect($manifest->artifacts)->firstWhere('identifier', 'ARTIFACT-INVOICE-000001');
    $invoice['revision'] = 3;
    $invoice['status'] = 'accepted';
    $invoice['references'] = [['identifier' => 'ARTIFACT-APPLICATION-000002', 'revision' => 1]];
    $contaminated = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, [...$manifest->artifacts, $invoice], $manifest->fingerprint, $manifest->canonicalFiles, $manifest->findings, $manifest->lifecycles);

    $builder->handle($root, $contaminated, new CompilationSubject('RESERVATION-000001', 'PropertyReservation'));
})->throws(CrossSubjectReferenceViolation::class);
