<?php

use Illuminate\Filesystem\Filesystem;

function validationRepositoryFixture(): string
{
    $source = dirname(__DIR__, 2);
    $root = sys_get_temp_dir().'/gne-validation-'.bin2hex(random_bytes(6));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root.'/resources');
    $files->copyDirectory($source.'/business', $root.'/business');
    $files->copyDirectory($source.'/resources/gne', $root.'/resources/gne');
    $files->copy($source.'/gne.yaml', $root.'/gne.yaml');
    $files->copy($source.'/GENEI.md', $root.'/GENEI.md');

    return $root;
}
