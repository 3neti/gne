<?php

namespace App\Domain\Repository;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;

final readonly class ExplainRepository
{
    public function __construct(private Filesystem $files, private ConnectionInterface $database) {}

    /** @return array<string, mixed> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest): array
    {
        $latestRun = $this->database->getSchemaBuilder()->hasTable('gne_materialization_runs') ? $this->database->table('gne_materialization_runs')->latest('id')->first() : null;
        $semanticPath = $repositoryRoot.'/'.$manifest->generatedPath.'/semantic/repository.json';

        return ['repository' => 'GNE repository-native Business Compiler', 'milestone' => 'Repository Integrity and Portability Hardening', 'thesis' => 'The business belongs to the repository. Everything operational is a projection.', 'self_description' => $this->files->exists($repositoryRoot.'/GENEI.md') ? 'GENEI.md' : null, 'canonical_source_path' => (string) $manifest->businessPath, 'generated_projection_path' => (string) $manifest->generatedPath, 'repository_fingerprint' => $manifest->fingerprint, 'validation' => ['valid' => ! $manifest->hasErrors(), 'findings' => count($manifest->findings)], 'profiles' => $manifest->profiles, 'scenarios' => $manifest->scenarios, 'artifact_types' => collect($manifest->artifacts)->pluck('type')->unique()->sort()->values()->all(), 'artifact_count' => count($manifest->artifacts), 'semantic_index' => ['available' => $this->files->exists($semanticPath), 'path' => str_replace(rtrim($repositoryRoot, '/').'/', '', $semanticPath)], 'materialization' => $latestRun ? ['status' => $latestRun->status, 'fingerprint' => $latestRun->repository_fingerprint, 'completed_at' => $latestRun->completed_at] : ['status' => 'not_run']];
    }
}
