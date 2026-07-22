<?php

use App\Domain\Compilation\BrowserProjectionDriver;
use App\Domain\Compilation\ResolvedDocument;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Repository\CanonicalRepositoryFingerprint;
use App\Domain\Repository\DiscoverRepository;
use App\Domain\Repository\RepositoryManifest;
use Illuminate\Filesystem\Filesystem;

function resolveBootstrapDocument(string $identifier): ResolvedDocument
{
    $root = dirname(__DIR__, 2);
    $files = new Filesystem;
    $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle($root);

    return (new ResolveDocument($files))->handle($root, $manifest, $identifier);
}

it('resolves ordered sections and fields from repository definitions', function () {
    $document = resolveBootstrapDocument('DOCUMENT-INVOICE');

    expect($document->title)->toBe('Reservation Invoice')
        ->and(array_map(fn ($section): string => $section->identifier, $document->sections))->toBe(['reservation', 'charges'])
        ->and(array_map(fn ($field): string => $field->identifier, $document->sections[1]->fields))->toBe(['amount', 'currency']);
});

it('selects the latest accepted artifact revision and preserves field evidence', function () {
    $document = resolveBootstrapDocument('DOCUMENT-INVOICE');
    $amount = $document->sections[1]->fields[0];

    expect($document->primaryArtifact->revision)->toBe(2)
        ->and($amount->value)->toBe(50000)
        ->and($amount->evidence->artifactIdentifier)->toBe('ARTIFACT-INVOICE-000001')
        ->and($amount->evidence->artifactRevision)->toBe(2)
        ->and($amount->evidence->valuePath)->toBe('payload.amount')
        ->and($amount->evidence->sourcePath)->toStartWith('business/');
});

it('resolves deterministically', function () {
    expect(resolveBootstrapDocument('DOCUMENT-RECEIPT')->toArray())
        ->toBe(resolveBootstrapDocument('DOCUMENT-RECEIPT')->toArray());
});

it('derives identity from the complete selected evidence set', function () {
    $root = dirname(__DIR__, 2);
    $files = new Filesystem;
    $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle($root);
    $resolver = new ResolveDocument($files);
    $original = $resolver->handle($root, $manifest, 'DOCUMENT-INVOICE');
    $applicationRevisionTwo = collect($manifest->artifacts)->firstWhere('identifier', 'ARTIFACT-APPLICATION-000001');
    $applicationRevisionTwo['revision'] = 2;
    $applicationRevisionTwo['payload']['applicant_alias'] = 'Revised Example Applicant';
    $revisedManifest = new RepositoryManifest(
        $manifest->businessPath,
        $manifest->generatedPath,
        $manifest->profiles,
        $manifest->scenarios,
        [...$manifest->artifacts, $applicationRevisionTwo],
        $manifest->fingerprint,
        $manifest->canonicalFiles,
        $manifest->findings,
    );
    $revised = $resolver->handle($root, $revisedManifest, 'DOCUMENT-INVOICE');
    $repeated = $resolver->handle($root, $revisedManifest, 'DOCUMENT-INVOICE');

    expect($original->primaryArtifact->revision)->toBe(2)
        ->and($revised->primaryArtifact->revision)->toBe(2)
        ->and($original->sections[0]->fields[0]->value)->toBe('Example Applicant')
        ->and($revised->sections[0]->fields[0]->value)->toBe('Revised Example Applicant')
        ->and($revised->resolutionFingerprint)->not->toBe($original->resolutionFingerprint)
        ->and($revised->identifier)->not->toBe($original->identifier)
        ->and($repeated->resolutionFingerprint)->toBe($revised->resolutionFingerprint)
        ->and($repeated->identifier)->toBe($revised->identifier);
});

it('does not change resolved identity for an unrelated repository fingerprint', function () {
    $root = dirname(__DIR__, 2);
    $files = new Filesystem;
    $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle($root);
    $resolver = new ResolveDocument($files);
    $unrelatedChange = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, $manifest->artifacts, str_repeat('f', 64), $manifest->canonicalFiles, $manifest->findings);

    expect($resolver->handle($root, $unrelatedChange, 'DOCUMENT-INVOICE')->identifier)
        ->toBe($resolver->handle($root, $manifest, 'DOCUMENT-INVOICE')->identifier);
});

it('projects browser output without changing resolved business meaning', function () {
    $document = resolveBootstrapDocument('DOCUMENT-APPLICATION');
    $projection = (new BrowserProjectionDriver)->project($document);

    expect($projection->title)->toBe($document->title)
        ->and($projection->sections[0]['fields'][0])->toBe($document->sections[0]->fields[0]->toArray())
        ->and($projection->metadata['document_definition'])->toBe($document->documentDefinition);
});
