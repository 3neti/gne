<?php

use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\DocumentResolutionRequest;
use App\Domain\Compilation\ResolvedDocument;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Compilation\SelectArtifactChain;
use App\Domain\Repository\CanonicalRepositoryFingerprint;
use App\Domain\Repository\DiscoverRepository;
use App\Domain\Repository\RepositoryManifest;
use App\Domain\Repository\RepositorySourceLoader;
use App\Domain\Repository\ValidateArtifactPayloads;
use App\Domain\Repository\ValidateDocumentDefinitions;
use App\Domain\Repository\ValidateRepository;
use App\Integration\XDocument\InvalidXDocumentCompilationRequest;
use App\Integration\XDocument\NormalizeXDocumentValue;
use App\Integration\XDocument\PrepareXDocumentCompilationRequest;
use App\Integration\XDocument\UnsupportedXDocumentContractVersion;
use App\Integration\XDocument\ValidateXDocumentCompilationRequest;
use App\Integration\XDocument\XDocumentCompilationResult;
use App\Integration\XDocument\XDocumentContractVersion;
use App\Integration\XDocument\XDocumentOutput;
use Illuminate\Filesystem\Filesystem;
use Opis\JsonSchema\Validator;

/** @return array{ResolvedDocument, RepositoryManifest} */
function xDocumentResolved(string $definition = 'DOCUMENT-INVOICE', string $subject = 'RESERVATION-000001'): array
{
    $root = dirname(__DIR__, 2);
    $files = new Filesystem;
    $loader = new RepositorySourceLoader($files);
    $manifest = (new ValidateRepository(new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)), new ValidateArtifactPayloads($loader), new ValidateDocumentDefinitions($loader)))->handle($root);
    $resolved = (new ResolveDocument(new SelectArtifactChain))->handle($root, $manifest, new DocumentResolutionRequest($definition, new CompilationSubject($subject, 'PropertyReservation')));

    return [$resolved, $manifest];
}

function xDocumentAdapter(): PrepareXDocumentCompilationRequest
{
    return new PrepareXDocumentCompilationRequest(new NormalizeXDocumentValue, new ValidateXDocumentCompilationRequest);
}

/** @param array<string, mixed> $changes */
function changedResolvedDocument(ResolvedDocument $document, array $changes): ResolvedDocument
{
    return new ResolvedDocument(
        $changes['identifier'] ?? $document->identifier,
        $document->title,
        $document->definitionIdentifier,
        $document->definitionRevision,
        $document->documentDefinition,
        $changes['resolution_fingerprint'] ?? $document->resolutionFingerprint,
        $document->compilationSubject,
        $document->primaryArtifact,
        $document->profile,
        $document->scenario,
        $document->status,
        $document->audience,
        $document->sections,
        $document->actions,
        $changes['attachments'] ?? $document->attachments,
        $document->evidence,
        $document->metadata,
    );
}

it('supports only contract version one', function () {
    expect((new XDocumentContractVersion)->value)->toBe('1.0');
    new XDocumentContractVersion('2.0');
})->throws(UnsupportedXDocumentContractVersion::class);

it('maps resolved meaning into a deterministic portable request', function () {
    [$resolved] = xDocumentResolved();
    $request = xDocumentAdapter()->handle($resolved);
    $payload = $request->toArray();

    expect($request->toJson())->toBe(xDocumentAdapter()->handle($resolved)->toJson())
        ->and($payload['contract_version'])->toBe('1.0')
        ->and($payload['document']['subject'])->toBe(['identifier' => 'RESERVATION-000001', 'type' => 'PropertyReservation'])
        ->and($payload['document']['primary_artifact']['identifier'])->toBe('ARTIFACT-INVOICE-000001')
        ->and($payload['document']['sections'][1]['fields'][0]['value'])->toBe(['type' => 'integer', 'value' => 50000])
        ->and($payload['document']['sections'][1]['fields'][0]['evidence'][0]['source_reference'])->toBe('business/profiles/property-reservation/examples/artifacts/invoice-000001-r2.yaml')
        ->and($payload['document']['actions'][0]['metadata']['execution_owner'])->toBe('host')
        ->and($request->requestIdentifier)->toBe('XDOC-REQUEST@'.$request->requestFingerprint);

    $json = $request->toJson();
    expect($json)->not->toContain('RepositoryManifest', 'SelectedArtifactChain', 'LifecyclePosition', 'App\\', 'Illuminate\\', 'source_path', 'database_id', 'password', 'token');
});

it('keeps request identity independent of unrelated repository state', function () {
    [$resolved, $manifest] = xDocumentResolved();
    $unrelated = new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, $manifest->artifacts, str_repeat('f', 64), $manifest->canonicalFiles, $manifest->findings, $manifest->lifecycles);
    $again = (new ResolveDocument(new SelectArtifactChain))->handle(dirname(__DIR__, 2), $unrelated, new DocumentResolutionRequest('DOCUMENT-INVOICE', new CompilationSubject('RESERVATION-000001', 'PropertyReservation')));

    expect(xDocumentAdapter()->handle($resolved)->toJson())->toBe(xDocumentAdapter()->handle($again)->toJson());
});

it('changes request identity when the resolved document fingerprint changes', function () {
    [$resolved] = xDocumentResolved();
    $changed = changedResolvedDocument($resolved, ['identifier' => 'DOCUMENT-INVOICE@'.str_repeat('b', 64), 'resolution_fingerprint' => str_repeat('b', 64)]);

    expect(xDocumentAdapter()->handle($changed)->requestFingerprint)->not->toBe(xDocumentAdapter()->handle($resolved)->requestFingerprint);
});

it('can omit evidence without losing resolved meaning', function () {
    [$resolved] = xDocumentResolved();
    $request = xDocumentAdapter()->handle($resolved, includeEvidence: false)->toArray();

    expect($request['options']['include_evidence'])->toBeFalse()
        ->and($request['document']['evidence'])->toBe([])
        ->and($request['document']['sections'][0]['fields'][0]['evidence'])->toBe([])
        ->and($request['requested_capabilities'])->not->toContain('evidence');
});

it('rejects unsafe values instead of coercing them', function (mixed $value) {
    (new NormalizeXDocumentValue)->handle($value);
})->with([
    'float' => 1.25,
    'object' => new stdClass,
])->throws(InvalidXDocumentCompilationRequest::class);

it('maps attachment metadata without reading or exposing absolute paths', function () {
    [$resolved] = xDocumentResolved();
    $withAttachment = changedResolvedDocument($resolved, ['attachments' => [['identifier' => 'terms', 'name' => 'Terms', 'media_type' => 'text/plain', 'checksum' => 'sha256:example', 'source_reference' => 'attachments/terms.txt']]]);
    $attachment = xDocumentAdapter()->handle($withAttachment)->toArray()['document']['attachments'][0];

    expect($attachment['source_reference'])->toBe('attachments/terms.txt')
        ->and($attachment)->not->toHaveKey('content');
});

it('rejects absolute attachment paths', function () {
    [$resolved] = xDocumentResolved();
    $withAttachment = changedResolvedDocument($resolved, ['attachments' => [['identifier' => 'terms', 'name' => 'Terms', 'source_reference' => '/private/terms.txt']]]);

    xDocumentAdapter()->handle($withAttachment);
})->throws(InvalidXDocumentCompilationRequest::class);

it('defines a serializable future result without compiling output', function () {
    $result = new XDocumentCompilationResult('1.0', 'request', 'document', str_repeat('a', 64), 'json', 'succeeded', new XDocumentOutput('application/json', inlineContent: '{}'));

    expect($result->toArray()['output']['inline_content'])->toBe('{}');
});

it('matches version one compatibility fixtures and validates them against the request schema', function (string $definition, string $fixture) {
    [$resolved] = xDocumentResolved($definition);
    $generated = xDocumentAdapter()->handle($resolved)->toJson();
    $fixtureJson = file_get_contents(dirname(__DIR__).'/Fixtures/XDocument/'.$fixture);
    $schema = json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/gne/contracts/x-document/1.0/compilation-request.schema.json'), false, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode($fixtureJson, false, flags: JSON_THROW_ON_ERROR);

    expect(json_decode($generated, true, flags: JSON_THROW_ON_ERROR))->toBe(json_decode($fixtureJson, true, flags: JSON_THROW_ON_ERROR))
        ->and((new Validator)->validate($payload, $schema)->isValid())->toBeTrue();
})->with([
    ['DOCUMENT-INVOICE', 'invoice-request.json'],
    ['DOCUMENT-RECEIPT', 'receipt-request.json'],
    ['DOCUMENT-RESERVATION-CERTIFICATE', 'reservation-certificate-request.json'],
]);

it('validates the prepared result shape against its versioned schema', function () {
    $result = new XDocumentCompilationResult('1.0', 'request', 'document', str_repeat('a', 64), 'json', 'succeeded', new XDocumentOutput('application/json', inlineContent: '{}'));
    $schema = json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/gne/contracts/x-document/1.0/compilation-result.schema.json'), false, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode(json_encode($result->toArray(), JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);

    expect((new Validator)->validate($payload, $schema)->isValid())->toBeTrue();
});
