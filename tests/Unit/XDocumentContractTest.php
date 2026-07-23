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
use App\Integration\XDocument\ValidateXDocumentSourceReference;
use App\Integration\XDocument\XDocumentCanonicalJson;
use App\Integration\XDocument\XDocumentCompilationRequest;
use App\Integration\XDocument\XDocumentCompilationResult;
use App\Integration\XDocument\XDocumentContractSchemas;
use App\Integration\XDocument\XDocumentContractVersion;
use App\Integration\XDocument\XDocumentOutput;
use Illuminate\Filesystem\Filesystem;

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
    return new PrepareXDocumentCompilationRequest(new NormalizeXDocumentValue, new ValidateXDocumentCompilationRequest, new XDocumentCanonicalJson, new ValidateXDocumentSourceReference);
}

/** @return array<string, mixed> */
function xDocumentFixturePayload(string $fixture = 'invoice-request.json'): array
{
    return json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/XDocument/'.$fixture), true, flags: JSON_THROW_ON_ERROR);
}

/** @param array<string, mixed> $payload */
function xDocumentSchemaAccepts(array $payload, string $schema): bool
{
    $prepare = function (mixed $value, ?string $key = null) use (&$prepare): mixed {
        if ($key === 'metadata' && $value === []) {
            return (object) [];
        }
        if (! is_array($value)) {
            return $value;
        }

        $prepared = [];
        foreach ($value as $itemKey => $item) {
            $prepared[$itemKey] = $prepare($item, (string) $itemKey);
        }

        return $prepared;
    };
    $data = json_decode(json_encode($prepare($payload), JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);

    return (new XDocumentContractSchemas)->validator()->validate($data, $schema)->isValid();
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
    $withAttachment = changedResolvedDocument($resolved, ['attachments' => [['identifier' => 'terms', 'name' => 'Terms', 'media_type' => 'text/plain', 'checksum' => 'sha256:'.str_repeat('a', 64), 'source_reference' => 'attachments/terms.txt']]]);
    $attachment = xDocumentAdapter()->handle($withAttachment)->toArray()['document']['attachments'][0];

    expect($attachment['source_reference'])->toBe('attachments/terms.txt')
        ->and($attachment)->not->toHaveKey('content');
});

it('rejects local filesystem attachment references', function (string $sourceReference) {
    [$resolved] = xDocumentResolved();
    $withAttachment = changedResolvedDocument($resolved, ['attachments' => [['identifier' => 'terms', 'name' => 'Terms', 'source_reference' => $sourceReference]]]);

    xDocumentAdapter()->handle($withAttachment);
})->with(['/private/terms.txt', 'C:\\private\\terms.txt', '\\\\server\\terms.txt', 'file:///private/terms.txt'])->throws(InvalidXDocumentCompilationRequest::class);

it('defines a serializable future result without compiling output', function () {
    $result = new XDocumentCompilationResult('1.0', 'XDOC-REQUEST@'.str_repeat('a', 64), 'document', str_repeat('a', 64), 'json', 'succeeded', new XDocumentOutput('application/json', inlineContent: '{}'));

    expect($result->toArray()['output']['inline_content'])->toBe('{}');
});

it('matches version one compatibility fixtures and validates them against the request schema', function (string $definition, string $fixture) {
    [$resolved] = xDocumentResolved($definition);
    $generated = xDocumentAdapter()->handle($resolved)->toJson();
    $fixtureJson = file_get_contents(dirname(__DIR__).'/Fixtures/XDocument/'.$fixture);
    $payload = json_decode($fixtureJson, true, flags: JSON_THROW_ON_ERROR);

    expect(json_decode($generated, true, flags: JSON_THROW_ON_ERROR))->toBe(json_decode($fixtureJson, true, flags: JSON_THROW_ON_ERROR))
        ->and(xDocumentSchemaAccepts($payload, XDocumentContractSchemas::Request))->toBeTrue()
        ->and(xDocumentSchemaAccepts($payload['document'], XDocumentContractSchemas::ResolvedDocument))->toBeTrue();
})->with([
    ['DOCUMENT-INVOICE', 'invoice-request.json'],
    ['DOCUMENT-RECEIPT', 'receipt-request.json'],
    ['DOCUMENT-RESERVATION-CERTIFICATE', 'reservation-certificate-request.json'],
]);

it('validates the prepared result shape against its versioned schema', function () {
    $result = new XDocumentCompilationResult('1.0', 'XDOC-REQUEST@'.str_repeat('a', 64), 'document', str_repeat('a', 64), 'json', 'succeeded', new XDocumentOutput('application/json', inlineContent: '{}'));

    expect(xDocumentSchemaAccepts($result->toArray(), XDocumentContractSchemas::Result))->toBeTrue();
});

it('uses the standalone resolved document as the only request document grammar', function () {
    $schema = json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/gne/contracts/x-document/1.0/compilation-request.schema.json'), true, flags: JSON_THROW_ON_ERROR);
    $payload = xDocumentFixturePayload();
    $payload['document']['sections'] = 'invalid';

    expect($schema['properties']['document'])->toBe(['$ref' => XDocumentContractSchemas::ResolvedDocument])
        ->and($schema)->not->toHaveKey('$defs')
        ->and(xDocumentSchemaAccepts($payload['document'], XDocumentContractSchemas::ResolvedDocument))->toBeFalse()
        ->and(xDocumentSchemaAccepts($payload, XDocumentContractSchemas::Request))->toBeFalse();
});

it('rejects discriminator and recursively invalid normalized values', function (array $value) {
    $payload = xDocumentFixturePayload();
    $payload['document']['sections'][0]['fields'][0]['value'] = $value;

    expect(xDocumentSchemaAccepts($payload, XDocumentContractSchemas::Request))->toBeFalse();
})->with([
    'integer containing string' => [['type' => 'integer', 'value' => '5']],
    'list containing arbitrary JSON' => [['type' => 'list', 'value' => [['arbitrary' => true]]]],
    'map containing mismatched boolean' => [['type' => 'map', 'value' => ['amount' => ['type' => 'boolean', 'value' => 10]]]],
]);

it('rejects malformed standalone resolved documents', function (Closure $mutate) {
    $document = xDocumentFixturePayload()['document'];
    $mutate($document);

    expect(xDocumentSchemaAccepts($document, XDocumentContractSchemas::ResolvedDocument))->toBeFalse();
})->with([
    'sections are not an array' => [function (array &$document): void {
        $document['sections'] = 'invalid';
    }],
    'field identifier missing' => [function (array &$document): void {
        unset($document['sections'][0]['fields'][0]['identifier']);
    }],
    'unknown core key' => [function (array &$document): void {
        $document['unexpected'] = true;
    }],
    'malformed fingerprint' => [function (array &$document): void {
        $document['resolution_fingerprint'] = 'short';
    }],
    'unsafe attachment reference' => [function (array &$document): void {
        $document['attachments'][] = ['identifier' => 'terms', 'name' => 'Terms', 'media_type' => null, 'byte_length' => null, 'checksum' => null, 'source_reference' => 'file:///terms.txt', 'disposition' => 'attachment', 'metadata' => (object) []];
    }],
    'unsafe primary artifact reference' => [function (array &$document): void {
        $document['primary_artifact']['source_reference'] = 'C:\\private\\artifact.yaml';
    }],
    'unsafe evidence reference' => [function (array &$document): void {
        $document['evidence'][0]['source_reference'] = '\\\\server\\artifact.yaml';
    }],
    'invalid evidence shape' => [function (array &$document): void {
        unset($document['evidence'][0]['artifact_identifier']);
    }],
]);

it('rejects malformed compilation requests', function (Closure $mutate) {
    $payload = xDocumentFixturePayload();
    $mutate($payload);

    expect(xDocumentSchemaAccepts($payload, XDocumentContractSchemas::Request))->toBeFalse();
})->with([
    'unknown contract version' => [function (array &$payload): void {
        $payload['contract_version'] = '2.0';
    }],
    'missing document' => [function (array &$payload): void {
        unset($payload['document']);
    }],
    'malformed capability' => [function (array &$payload): void {
        $payload['requested_capabilities'] = [10];
    }],
    'unknown core key' => [function (array &$payload): void {
        $payload['unexpected'] = true;
    }],
]);

it('enforces result status and output compatibility', function (string $status, ?XDocumentOutput $output, bool $valid) {
    $result = new XDocumentCompilationResult('1.0', 'XDOC-REQUEST@'.str_repeat('a', 64), 'document', str_repeat('b', 64), 'json', $status, $output);

    expect(xDocumentSchemaAccepts($result->toArray(), XDocumentContractSchemas::Result))->toBe($valid);
})->with([
    'succeeded inline' => ['succeeded', new XDocumentOutput('application/json', checksum: str_repeat('a', 64), byteLength: 2, inlineContent: '{}'), true],
    'unsupported without output' => ['unsupported', null, true],
    'failed without output' => ['failed', null, true],
    'succeeded without output' => ['succeeded', null, false],
    'failed with output' => ['failed', new XDocumentOutput('application/json', inlineContent: '{}'), false],
    'malformed checksum' => ['succeeded', new XDocumentOutput('application/json', checksum: 'invalid', inlineContent: '{}'), false],
    'negative byte length' => ['succeeded', new XDocumentOutput('application/json', byteLength: -1, inlineContent: '{}'), false],
    'two output forms' => ['succeeded', new XDocumentOutput('application/json', inlineContent: '{}', contentReference: 'outputs/document.json'), false],
]);

it('canonicalizes map keys recursively while preserving list order', function () {
    $canonical = new XDocumentCanonicalJson;

    expect($canonical->encode(['z' => ['b' => 2, 'a' => 1], 'a' => [['z' => 1, 'a' => 2], 'last']]))
        ->toBe($canonical->encode(['a' => [['a' => 2, 'z' => 1], 'last'], 'z' => ['a' => 1, 'b' => 2]]));
});

it('propagates generated contract defects instead of reporting repository findings', function () {
    [$resolved] = xDocumentResolved();
    $valid = xDocumentAdapter()->handle($resolved);
    $invalid = new XDocumentCompilationRequest($valid->contractVersion, $valid->requestIdentifier, $valid->requestFingerprint, $valid->correlationIdentifier, $valid->requestedDriver, $valid->requestedCapabilities, ['include_evidence' => 'yes'], $valid->document);

    (new ValidateXDocumentCompilationRequest)->handle($invalid);
})->throws(InvalidXDocumentCompilationRequest::class);
