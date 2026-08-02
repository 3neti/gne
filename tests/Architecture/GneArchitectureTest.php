<?php

use Illuminate\Database\Eloquent\Model;

arch('domain primitives are framework independent')
    ->expect('App\Domain\Artifacts')
    ->not->toExtend(Model::class)
    ->not->toUse('Illuminate\Database');

arch('repository discovery does not use the database')
    ->expect('App\Domain\Repository\DiscoverRepository')
    ->not->toUse('Illuminate\Database');

arch('commands delegate repository behavior')
    ->expect('App\Console\Commands')
    ->toOnlyUse([
        'App\Domain',
        'App\Integration',
        'App\Application',
        'App\Infrastructure',
        'Illuminate\Console',
        'Illuminate\Filesystem',
        'Illuminate\Support',
        'Illuminate\Contracts',
        'LBHurtado\XDocument\Browser\Host',
        'LBHurtado\XDocumentLaravel\Contracts',
        'JsonException',
        'base_path',
        'collect',
        'config',
        'route',
    ]);

it('keeps canonical source and generated projections in separate roots', function () {
    $root = dirname(__DIR__, 2);
    expect($root.'/business')->toBeDirectory()
        ->and($root.'/.gne')->toBeDirectory()
        ->and(realpath($root.'/business'))->not->toStartWith(realpath($root.'/.gne'));
});

it('does not couple core source to external settlement or PDF implementations', function () {
    $core = collect((new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Domain'))))->filter(fn (SplFileInfo $file): bool => $file->isFile())->map(fn (SplFileInfo $file): string => file_get_contents($file->getPathname()))->implode("\n");
    expect($core)->not->toContain('Adobe')
        ->and($core)->not->toContain('Pay Code')
        ->and($core)->not->toContain('Wallet');
});

arch('resolved document IR has no browser or framework dependencies')
    ->expect([
        'App\Domain\Compilation\ResolvedDocument',
        'App\Domain\Compilation\ResolvedSection',
        'App\Domain\Compilation\ResolvedField',
        'App\Domain\Compilation\ResolvedAction',
        'App\Domain\Compilation\DocumentEvidence',
        'App\Domain\Compilation\CompilationSubject',
        'App\Domain\Compilation\SelectedArtifactChain',
    ])
    ->not->toUse(['Illuminate', 'Inertia', 'Vue', 'Tailwind', 'App\Http']);

arch('browser projection driver consumes the resolved document IR')
    ->expect('App\Domain\Compilation\BrowserProjectionDriver')
    ->toUse('App\Domain\Compilation\ResolvedDocument')
    ->not->toUse(['App\Http', 'Inertia', 'Vue']);

arch('document set IR and lifecycle inventory are framework independent')
    ->expect([
        'App\Domain\Compilation\ResolvedDocumentSet',
        'App\Domain\Compilation\DocumentInventoryEntry',
        'App\Domain\Compilation\DocumentReadiness',
        'App\Domain\Compilation\LifecyclePosition',
        'App\Domain\Compilation\MissingDocumentEvidence',
        'App\Domain\Compilation\BuildResolvedDocumentSet',
    ])
    ->not->toUse(['Illuminate\Database', 'Illuminate\Database\Eloquent', 'Inertia', 'Vue', 'App\Http']);

arch('document set browser driver only projects prepared inventory')
    ->expect('App\Domain\Compilation\DocumentSetBrowserProjectionDriver')
    ->toUse('App\Domain\Compilation\ResolvedDocumentSet')
    ->not->toUse([
        'App\Domain\Compilation\ResolveDocument',
        'App\Domain\Compilation\SelectArtifactChain',
        'App\Domain\Repository\DiscoverRepository',
        'App\Domain\Repository\ValidateRepository',
        'Inertia',
    ]);

arch('artifact chain selection is independent of persistence')
    ->expect('App\Domain\Compilation\SelectArtifactChain')
    ->not->toUse(['Illuminate\Database', 'Illuminate\Database\Eloquent']);

arch('authoring validators remain outside artifact chain selection')
    ->expect('App\Domain\Compilation\SelectArtifactChain')
    ->not->toUse([
        'App\Domain\Repository\ValidateArtifactPayloads',
        'App\Domain\Repository\ValidateDocumentDefinitions',
        'Opis\JsonSchema',
    ]);

arch('authoring validators catch only classified source and schema failures')
    ->expect([
        'App\Domain\Repository\ValidateArtifactPayloads',
        'App\Domain\Repository\ValidateDocumentDefinitions',
    ])
    ->not->toUse('Throwable');

arch('browser driver does not discover or validate repository source')
    ->expect('App\Domain\Compilation\BrowserProjectionDriver')
    ->not->toUse([
        'App\Domain\Repository\DiscoverRepository',
        'App\Domain\Repository\ValidateRepository',
        'App\Domain\Repository\ValidateDocumentDefinitions',
    ]);

it('keeps global artifact selection out of document resolution', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Compilation/ResolveDocument.php');

    expect($source)->toContain('SelectArtifactChain')
        ->not->toContain('$manifest->artifacts');
});

arch('compilation planning delegates readiness classification to the document set builder')
    ->expect('App\Domain\Compilation\PrepareCompilationPlan')
    ->toUse('App\Domain\Compilation\BuildResolvedDocumentSet')
    ->not->toUse('Throwable');

arch('document readiness catches no unclassified implementation failures')
    ->expect('App\Domain\Compilation\BuildResolvedDocumentSet')
    ->not->toUse('Throwable');

it('classifies only missing evidence as ordinary document readiness', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Compilation/BuildResolvedDocumentSet.php');

    expect($source)->toContain('catch (DocumentEvidenceNotFound $exception)')
        ->not->toContain('catch (AmbiguousArtifactSelection', 'catch (CrossSubjectReferenceViolation');
});

it('derives document set identity from direct inputs rather than the repository fingerprint', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Compilation/BuildResolvedDocumentSet.php');

    expect($source)->not->toContain('$manifest->fingerprint', 'repository_fingerprint');
});

arch('x-document transfer DTOs remain independent of GNE compiler and persistence classes')
    ->expect([
        'App\Integration\XDocument\XDocumentContractVersion',
        'App\Integration\XDocument\XDocumentSubject',
        'App\Integration\XDocument\XDocumentEvidence',
        'App\Integration\XDocument\XDocumentField',
        'App\Integration\XDocument\XDocumentSection',
        'App\Integration\XDocument\XDocumentAction',
        'App\Integration\XDocument\XDocumentAttachment',
        'App\Integration\XDocument\XDocumentResolvedDocument',
        'App\Integration\XDocument\XDocumentCompilationRequest',
        'App\Integration\XDocument\XDocumentOutput',
        'App\Integration\XDocument\XDocumentCompilationResult',
    ])
    ->not->toUse([
        'App\Domain',
        'App\Models',
        'Illuminate\Database',
        'Illuminate\Http',
        'App\Domain\Compilation\ResolvedDocument',
    ]);

arch('only the x-document adapter maps the internal compiler IR')
    ->expect('App\Integration\XDocument\PrepareXDocumentCompilationRequest')
    ->toUse('App\Domain\Compilation\ResolvedDocument')
    ->not->toUse([
        'App\Domain\Repository',
        'App\Domain\Compilation\SelectedArtifactChain',
        'App\Domain\Compilation\LifecyclePosition',
        'Illuminate\Filesystem',
        'App\Domain\Compilation\BrowserProjectionDriver',
    ]);

it('keeps repository machinery and PDF semantics out of the external transfer contract', function () {
    $root = dirname(__DIR__, 2);
    $contract = collect((new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Integration/XDocument'))))
        ->filter(fn (SplFileInfo $file): bool => $file->isFile())
        ->map(fn (SplFileInfo $file): string => file_get_contents($file->getPathname()))
        ->implode("\n");
    $composer = file_get_contents($root.'/composer.json');

    expect($contract)->not->toContain('RepositoryManifest', 'SelectedArtifactChain', 'LifecyclePosition', 'Adobe', 'AcroForm')
        ->and($composer)->toContain('3neti/x-document', '3neti/x-document-laravel');
});

arch('the runtime adapter is the only GNE service coupled to x-document representation APIs')
    ->expect('App\Integration\XDocument\ResolveXDocumentBrowserRepresentation')
    ->toUse([
        'App\Domain\Compilation\ResolveDocument',
        'App\Integration\XDocument\PrepareXDocumentCompilationRequest',
        'LBHurtado\XDocument\Contract\ValidateDocumentCompilationRequest',
        'LBHurtado\XDocument\Browser\Host\ResolveBrowserRepresentation',
    ])
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Database',
        'App\Models',
        'LBHurtado\XDocumentLaravel',
        'Throwable',
    ]);

it('records exact reviewed runtime package baselines', function () {
    $attestor = file_get_contents(dirname(__DIR__, 2).'/app/Integration/XDocument/XDocumentPackageBaselineAttestor.php');

    expect($attestor)
        ->toContain('29853fae23939cba0b440db3ae04e351c499a78e')
        ->toContain('b299d5bfbe7bdf93ecaf840431349804b676a6c7');
});

arch('the HTTP controller coordinates GNE resolution and x-document-laravel delivery only')
    ->expect('App\Http\Controllers\ShowCompiledBrowserDocumentController')
    ->toUse([
        'App\Integration\XDocument\BrowserDocumentRepresentationResolver',
        'LBHurtado\XDocumentLaravel\Contracts\DocumentHttpResponseFactory',
        'LBHurtado\XDocumentLaravel\Data\DocumentHttpRequestContext',
    ])
    ->not->toUse([
        'App\Domain\Repository',
        'App\Domain\Compilation\ResolveDocument',
        'App\Integration\XDocument\PrepareXDocumentCompilationRequest',
        'Symfony\Component\Process',
        'Illuminate\Database',
        'Inertia',
    ]);

it('keeps rendering, serialization, entity-tag construction, and Git execution out of request handling', function () {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ShowCompiledBrowserDocumentController.php');

    expect($controller)
        ->not->toContain('<html', 'json_encode', 'Content-Disposition', 'Content-Length', "hash('sha256'", 'new Process', 'git ')
        ->and($controller)->toContain('DocumentHttpResponseFactory', 'DocumentHttpRequestContext::fromLaravelRequest');
});

it('keeps browser workbench navigation readiness-driven and free of representation rendering', function () {
    $root = dirname(__DIR__, 2);
    $workbench = file_get_contents($root.'/resources/js/pages/DocumentSetWorkbench.vue');

    expect($workbench)
        ->toContain("entry.readiness === 'resolved'", 'showBrowserDocument.url', 'Open Unified Browser Document')
        ->not->toContain('v-html', '<iframe', 'fetch(');
});

arch('dependency baseline attestation remains outside the browser request path')
    ->expect([
        'App\Http\Controllers\ShowCompiledBrowserDocumentController',
        'App\Integration\XDocument\ResolveXDocumentBrowserRepresentation',
    ])
    ->not->toUse([
        'App\Integration\XDocument\XDocumentPackageBaselineAttestor',
        'App\Integration\XDocument\ResolveInstalledPackageGitHead',
        'Symfony\Component\Process',
    ]);

it('versions the x-document contract schema in its repository path', function () {
    $root = dirname(__DIR__, 2).'/resources/gne/contracts/x-document/1.0';
    $request = json_decode(file_get_contents($root.'/compilation-request.schema.json'), true, flags: JSON_THROW_ON_ERROR);
    $document = json_decode(file_get_contents($root.'/resolved-document.schema.json'), true, flags: JSON_THROW_ON_ERROR);
    $result = json_decode(file_get_contents($root.'/compilation-result.schema.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($root.'/compilation-request.schema.json')->toBeFile()
        ->and($root.'/compilation-result.schema.json')->toBeFile()
        ->and($request['$id'])->toBe('https://3neti.dev/contracts/x-document/1.0/compilation-request.schema.json')
        ->and($document['$id'])->toBe('https://3neti.dev/contracts/x-document/1.0/resolved-document.schema.json')
        ->and($result['$id'])->toBe('https://3neti.dev/contracts/x-document/1.0/compilation-result.schema.json')
        ->and($request['properties']['document'])->toBe(['$ref' => $document['$id']])
        ->and($request)->not->toHaveKey('$defs');
});

arch('storyboard domain models remain driver neutral and persistence free')
    ->expect('App\Domain\Storyboard')
    ->not->toUse(['App\Models', 'App\Integration\XDocument', 'Illuminate\Database\Eloquent', 'Illuminate\Http', 'Inertia', 'LBHurtado\XDocument', 'LBHurtado\XDocumentLaravel']);

it('keeps demonstration choreography outside canonical business source', function () {
    expect(dirname(__DIR__, 2).'/docs/mvp/storyboards/property-reservation-mvp.yaml')->toBeFile()
        ->and(dirname(__DIR__, 2).'/docs/mvp/STORYBOARD_REFERENCE_REVIEW.md')->toBeFile()
        ->and(dirname(__DIR__, 2).'/business/profiles/property-reservation/storyboards')->not->toBeDirectory();
});

arch('storyboard rendition infrastructure consumes finalized evidence without compiling business meaning')
    ->expect([
        'App\Infrastructure\Storyboard\StoryboardHtmlRenderer',
        'App\Infrastructure\Storyboard\PrintStoryboardPdf',
    ])
    ->not->toUse([
        'App\Domain\Repository',
        'App\Domain\Compilation',
        'App\Integration\XDocument',
        'App\Models',
        'Illuminate\Database',
        'Illuminate\Http',
    ]);

it('keeps one post-capture HTML-to-PDF path and no metadata-only storyboard PDF renderer', function () {
    $root = dirname(__DIR__, 2);
    $command = file_get_contents($root.'/app/Console/Commands/GneStoryboardCommand.php');

    expect($root.'/app/Infrastructure/Storyboard/StoryboardPdfRenderer.php')->not->toBeFile()
        ->and($command)->toContain('StoryboardHtmlRenderer', 'PrintStoryboardPdf')
        ->and($command)->not->toContain('StoryboardPdfRenderer');
});
