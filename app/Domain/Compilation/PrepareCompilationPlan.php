<?php

namespace App\Domain\Compilation;

use App\Domain\Repository\RepositoryManifest;

final class PrepareCompilationPlan
{
    /** @return array<string, mixed> */
    public function handle(RepositoryManifest $manifest): array
    {
        return ['status' => 'planned', 'profiles' => count($manifest->profiles), 'scenarios' => count($manifest->scenarios), 'drivers' => ['browser' => ['available' => true, 'scope' => 'workbench projection plan'], 'document' => ['available' => false, 'reason' => 'x-document is not installed'], 'settlement' => ['available' => false, 'reason' => 'x-change is not configured']], 'notice' => 'This is a compilation plan, not a completed business compilation.'];
    }
}
