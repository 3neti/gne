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
