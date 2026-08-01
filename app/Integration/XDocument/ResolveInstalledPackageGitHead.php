<?php

namespace App\Integration\XDocument;

use Symfony\Component\Process\Process;

class ResolveInstalledPackageGitHead
{
    public function handle(string $sourcePath): ?string
    {
        $process = new Process(['git', '-C', $sourcePath, 'rev-parse', 'HEAD']);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $commit = trim($process->getOutput());

        return preg_match('/^[a-f0-9]{40}$/D', $commit) === 1 ? $commit : null;
    }
}
