<?php

namespace App\Domain\Materialization;

use App\Domain\Repository\RepositoryManifest;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Throwable;

final readonly class MaterializeRepository
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return array<string, mixed> */
    public function handle(RepositoryManifest $manifest): array
    {
        $startedAt = Carbon::now();
        $fingerprint = $manifest->fingerprint;
        $runId = $this->database->table('gne_materialization_runs')->insertGetId(['repository_fingerprint' => $fingerprint, 'status' => 'running', 'started_at' => $startedAt, 'created_at' => $startedAt, 'updated_at' => $startedAt]);

        try {
            $counts = $this->database->transaction(function () use ($manifest): array {
                $this->replaceProjection('gne_profiles', $manifest->profiles, fn (array $item): array => ['repository_identifier' => $item['identifier'], 'title' => $item['title'], 'source_path' => $item['path'], 'metadata' => json_encode($item, JSON_THROW_ON_ERROR)]);
                $this->replaceProjection('gne_scenarios', $manifest->scenarios, fn (array $item): array => ['repository_identifier' => $item['identifier'], 'profile_identifier' => $item['profile'], 'title' => $item['title'], 'source_path' => $item['path'], 'metadata' => json_encode($item, JSON_THROW_ON_ERROR)]);
                $this->replaceProjection('gne_artifacts', $manifest->artifacts, fn (array $item): array => ['repository_identifier' => $item['identifier'], 'revision' => (string) $item['revision'], 'artifact_type' => $item['type'], 'profile_identifier' => $item['profile'], 'scenario_identifier' => $item['scenario'], 'status' => $item['status'], 'source_path' => $item['path'], 'metadata' => json_encode($item, JSON_THROW_ON_ERROR)]);
                $relationships = collect($manifest->artifacts)->flatMap(fn (array $artifact): array => collect($artifact['references'])->map(function (mixed $reference) use ($artifact): array {
                    $reference = is_array($reference) ? $reference : ['identifier' => $reference];

                    return ['source_identifier' => $artifact['identifier'], 'source_revision' => (string) $artifact['revision'], 'relationship_type' => $reference['relationship'] ?? 'references', 'target_identifier' => $reference['identifier'] ?? $reference['artifact'], 'target_revision' => isset($reference['revision']) ? (string) $reference['revision'] : null, 'source_path' => $artifact['path']];
                })->all())->all();
                $this->replaceProjection('gne_artifact_relationships', $relationships, fn (array $item): array => $item);

                return ['profiles' => count($manifest->profiles), 'scenarios' => count($manifest->scenarios), 'artifacts' => count($manifest->artifacts), 'relationships' => count($relationships)];
            });
            $this->database->table('gne_materialization_runs')->where('id', $runId)->update(['status' => 'completed', 'completed_at' => Carbon::now(), 'counts' => json_encode($counts, JSON_THROW_ON_ERROR), 'updated_at' => Carbon::now()]);

            return ['run_id' => $runId, 'fingerprint' => $fingerprint, 'status' => 'completed', 'counts' => $counts];
        } catch (Throwable $exception) {
            $this->database->table('gne_materialization_runs')->where('id', $runId)->update(['status' => 'failed', 'completed_at' => Carbon::now(), 'errors' => json_encode([$exception->getMessage()], JSON_THROW_ON_ERROR), 'updated_at' => Carbon::now()]);
            throw $exception;
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function replaceProjection(string $table, array $items, callable $map): void
    {
        $this->database->table($table)->delete();
        if ($items !== []) {
            $now = Carbon::now();
            $this->database->table($table)->insert(array_map(fn (array $item): array => [...$map($item), 'created_at' => $now, 'updated_at' => $now], $items));
        }
    }
}
