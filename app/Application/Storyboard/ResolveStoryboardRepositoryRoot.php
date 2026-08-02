<?php

namespace App\Application\Storyboard;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;

final readonly class ResolveStoryboardRepositoryRoot
{
    public function __construct(private Filesystem $files) {}

    public function handle(Request $request): string
    {
        $default = base_path();
        if (! app()->environment(['local', 'testing']) || ! $request->user()) {
            return $default;
        }
        $storyboard = $request->header('X-GNE-Storyboard');
        $stage = $request->header('X-GNE-Storyboard-Stage');
        if (! is_string($storyboard) || ! is_string($stage)
            || preg_match('/^[a-z0-9-]+$/', $storyboard) !== 1
            || preg_match('/^[a-z0-9-]+$/', $stage) !== 1
        ) {
            return $default;
        }
        $candidate = storage_path("framework/gne-storyboards/{$storyboard}/states/{$stage}");
        $resolved = realpath($candidate);
        $allowed = realpath(storage_path('framework/gne-storyboards'));
        if ($resolved === false || $allowed === false
            || ! str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)
            || ! $this->files->exists($resolved.'/gne.yaml')
            || ! $this->files->isDirectory($resolved.'/business')
        ) {
            return $default;
        }

        return $resolved;
    }
}
