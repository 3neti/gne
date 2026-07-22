<?php

use App\Domain\Compilation\BrowserProjectionDriver;
use App\Domain\Compilation\ResolvedDocument;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Repository\CanonicalRepositoryFingerprint;
use App\Domain\Repository\DiscoverRepository;
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

    expect($document->revision)->toBe(2)
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

it('projects browser output without changing resolved business meaning', function () {
    $document = resolveBootstrapDocument('DOCUMENT-APPLICATION');
    $projection = (new BrowserProjectionDriver)->project($document);

    expect($projection->title)->toBe($document->title)
        ->and($projection->sections[0]['fields'][0])->toBe($document->sections[0]->fields[0]->toArray())
        ->and($projection->metadata['document_definition'])->toBe($document->documentDefinition);
});
