<?php

use App\Domain\Repository\ValidateRepository;
use Illuminate\Filesystem\Filesystem;

require_once dirname(__DIR__).'/Support/ValidationRepositoryFixture.php';

it('reports accepted artifact payload schema violations with repository evidence', function () {
    $root = validationRepositoryFixture();
    $files = new Filesystem;
    $path = $root.'/business/profiles/property-reservation/examples/artifacts/invoice-000002-r1.yaml';
    $files->put($path, str_replace('amount: 75000', 'amount: wrong', $files->get($path)));

    try {
        $finding = collect(app(ValidateRepository::class)->handle($root)->findings)->firstWhere('code', 'ARTIFACT_PAYLOAD_SCHEMA_VIOLATION');

        expect($finding)->not->toBeNull()
            ->and($finding->sourcePath)->toBe('business/profiles/property-reservation/examples/artifacts/invoice-000002-r1.yaml')
            ->and($finding->location)->toBe('/payload/amount')
            ->and($finding->context['schema_path'])->toBe('business/profiles/property-reservation/schemas/invoice.schema.json')
            ->and($finding->context['artifact_identifier'])->toBe('ARTIFACT-INVOICE-000002');
    } finally {
        $files->deleteDirectory($root);
    }
});

it('reports malformed artifact schemas precisely', function () {
    $root = validationRepositoryFixture();
    $files = new Filesystem;
    $schema = $root.'/business/profiles/property-reservation/schemas/invoice.schema.json';
    $files->put($schema, str_replace('"type":"number"', '"type":"not-a-json-schema-type"', $files->get($schema)));

    try {
        $finding = collect(app(ValidateRepository::class)->handle($root)->findings)->firstWhere('code', 'ARTIFACT_SCHEMA_INVALID');

        expect($finding)->not->toBeNull()
            ->and($finding->context['schema_path'])->toEndWith('invoice.schema.json');
    } finally {
        $files->deleteDirectory($root);
    }
});

it('reports malformed document YAML without running contextual validation', function () {
    $root = validationRepositoryFixture();
    $files = new Filesystem;
    $path = $root.'/business/profiles/property-reservation/documents/invoice.yaml';
    $files->put($path, "identifier: DOCUMENT-INVOICE\nsections: [\n");

    try {
        $findings = collect(app(ValidateRepository::class)->handle($root)->findings)->filter(fn ($finding): bool => $finding->sourcePath === 'business/profiles/property-reservation/documents/invoice.yaml');

        expect($findings->where('code', 'DOCUMENT_DEFINITION_INVALID'))->toHaveCount(1)
            ->and($findings->pluck('code'))->not->toContain('DOCUMENT_FIELD_PATH_UNKNOWN', 'DOCUMENT_ARTIFACT_TYPE_UNKNOWN');
    } finally {
        $files->deleteDirectory($root);
    }
});

it('reports a missing declared schema as an authoring finding', function () {
    $root = validationRepositoryFixture();
    $files = new Filesystem;
    $files->delete($root.'/business/profiles/property-reservation/schemas/invoice.schema.json');

    try {
        $finding = collect(app(ValidateRepository::class)->handle($root)->findings)->first(fn ($finding): bool => $finding->code === 'ARTIFACT_SCHEMA_INVALID' && isset($finding->context['schema_path']));

        expect($finding)->not->toBeNull()
            ->and($finding->context['schema_path'])->toEndWith('invoice.schema.json');
    } finally {
        $files->deleteDirectory($root);
    }
});

it('rejects duplicate fields, unknown artifact types, and unknown payload paths', function () {
    $root = validationRepositoryFixture();
    $files = new Filesystem;
    $path = $root.'/business/profiles/property-reservation/documents/invoice.yaml';
    $definition = $files->get($path);
    $definition = str_replace('path: payload.amount', 'path: payload.not_declared', $definition);
    $definition = str_replace('identifier: currency', 'identifier: amount', $definition);
    $definition = str_replace('artifacts: [Application, Invoice]', 'artifacts: [Application, Invoice, UnknownArtifact]', $definition);
    $files->put($path, $definition);

    try {
        $codes = collect(app(ValidateRepository::class)->handle($root)->findings)->pluck('code');

        expect($codes)->toContain('DOCUMENT_FIELD_PATH_UNKNOWN')
            ->toContain('DOCUMENT_FIELD_IDENTIFIER_DUPLICATE')
            ->toContain('DOCUMENT_ARTIFACT_TYPE_UNKNOWN');
    } finally {
        $files->deleteDirectory($root);
    }
});

it('orders validation findings deterministically with errors before warnings', function () {
    $root = validationRepositoryFixture();
    $files = new Filesystem;
    $path = $root.'/business/profiles/property-reservation/documents/invoice.yaml';
    $files->put($path, str_replace('payload.amount', 'payload.unknown', $files->get($path)));

    try {
        $validator = app(ValidateRepository::class);
        $first = array_map(fn ($finding): array => $finding->toArray(), $validator->handle($root)->findings);
        $second = array_map(fn ($finding): array => $finding->toArray(), $validator->handle($root)->findings);

        expect($first)->toBe($second)
            ->and($first[0]['severity'])->toBe('error');
    } finally {
        $files->deleteDirectory($root);
    }
});
