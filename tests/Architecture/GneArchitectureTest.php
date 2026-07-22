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
        'Illuminate\Console',
        'Illuminate\Filesystem',
        'Illuminate\Support',
        'JsonException',
        'base_path',
        'collect',
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

arch('artifact chain selection is independent of persistence')
    ->expect('App\Domain\Compilation\SelectArtifactChain')
    ->not->toUse(['Illuminate\Database', 'Illuminate\Database\Eloquent']);

it('keeps global artifact selection out of document resolution', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Compilation/ResolveDocument.php');

    expect($source)->toContain('SelectArtifactChain')
        ->not->toContain('$manifest->artifacts');
});

arch('compilation planning catches only expected document resolution failures')
    ->expect('App\Domain\Compilation\PrepareCompilationPlan')
    ->toUse('App\Domain\Compilation\DocumentResolutionException')
    ->not->toUse('Throwable');
