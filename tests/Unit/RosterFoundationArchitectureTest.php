<?php

use Illuminate\Filesystem\Filesystem;

it('keeps prohibited generation import and integration machinery out of rostering', function () {
    $files = new Filesystem;
    $root = dirname(__DIR__, 2);
    $roots = ["{$root}/app/Application/Rostering", "{$root}/app/Domain/Rostering", "{$root}/resources/js/pages/rostering", "{$root}/business/profiles/anaesthesia-rostering"];
    $controllerFiles = $files->glob("{$root}/app/Http/Controllers/*Roster*.php");
    $source = collect($roots)->flatMap(fn (string $root) => $files->allFiles($root))->map(fn ($file): string => $file->getContents())->merge(array_map(fn (string $path): string => $files->get($path), $controllerFiles))->implode("\n");

    expect($source)->not->toContain('RosterGenerator')
        ->not->toContain('OR-Tools')
        ->not->toContain('x-optimization')
        ->not->toContain('LBHurtado\\XChange')
        ->not->toContain('LBHurtado\\XDocument')
        ->not->toContain('PhpSpreadsheet')
        ->not->toContain('on_call')
        ->not->toContain('second_call');
});

it('keeps active duty vocabulary deliberately small', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Domain/Rostering/DutyCode.php'))->toContain('StandardDay', 'Leave', 'Unavailable')
        ->not->toContain('OnCall')
        ->not->toContain('Overtime');
});
