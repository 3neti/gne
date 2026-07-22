<?php

namespace App\Domain\Semantics;

use App\Domain\Compilation\BuildResolvedDocumentSet;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Repository\RepositoryManifest;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final readonly class BuildSemanticIndex
{
    public function __construct(private Filesystem $files, private BuildResolvedDocumentSet $documentSetBuilder) {}

    /** @return array<string, int> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest): array
    {
        $directory = $repositoryRoot.'/'.$manifest->generatedPath.'/semantic';
        $this->files->ensureDirectoryExists($directory);

        $profiles = $this->sorted($manifest->profiles);
        $scenarios = $this->sorted($manifest->scenarios);
        $artifacts = collect($manifest->artifacts)->sortBy([['identifier', 'asc'], ['revision', 'asc']])->values()->all();
        $subjects = collect($artifacts)->filter(fn (array $artifact): bool => is_array($artifact['subject'] ?? null))->groupBy('subject.identifier')->map(function ($subjectArtifacts, string $identifier): array {
            $first = $subjectArtifacts->first();

            return ['identifier' => $identifier, 'type' => $first['subject']['type'], 'profile' => $first['profile'], 'scenarios' => $subjectArtifacts->pluck('scenario')->unique()->sort()->values()->all(), 'artifact_count' => $subjectArtifacts->count(), 'artifact_types' => $subjectArtifacts->pluck('type')->unique()->sort()->values()->all()];
        })->sortBy('identifier')->values()->all();
        $artifactTypes = [];
        foreach ($profiles as $profile) {
            foreach ($profile['artifact_types'] as $type => $declaration) {
                $scenarioIdentifiers = array_values(array_map(fn (array $scenario): string => $scenario['identifier'], array_filter($scenarios, fn (array $scenario): bool => $scenario['profile'] === $profile['identifier'])));
                sort($scenarioIdentifiers);
                $acceptedCount = count(array_filter($artifacts, fn (array $artifact): bool => $artifact['profile'] === $profile['identifier'] && $artifact['type'] === $type && $artifact['status'] === 'accepted'));
                $artifactTypes[] = ['type' => $type, 'profile' => $profile['identifier'], 'schema' => dirname($profile['path']).'/'.$declaration['schema'], 'scenarios' => $scenarioIdentifiers, 'accepted_artifact_count' => $acceptedCount];
            }
        }
        usort($artifactTypes, fn (array $left, array $right): int => [$left['profile'], $left['type']] <=> [$right['profile'], $right['type']]);
        $lifecycles = collect($manifest->lifecycles)->sortBy('identifier')->values()->all();
        $documentSets = collect($subjects)->map(function (array $subject) use ($repositoryRoot, $manifest): array {
            $set = $this->documentSetBuilder->handle($repositoryRoot, $manifest, new CompilationSubject($subject['identifier'], $subject['type']));

            return ['subject' => $subject['identifier'], 'subject_type' => $subject['type'], 'profile' => $set->profile, 'scenario' => $set->scenario, 'lifecycle' => $set->lifecyclePosition->toArray(), 'counts' => $set->toArray()['counts'], 'fingerprint' => $set->fingerprint];
        })->all();
        $relationships = [];
        foreach ($artifacts as $artifact) {
            foreach ($artifact['references'] as $reference) {
                $reference = is_array($reference) ? $reference : ['identifier' => $reference];
                $relationships[] = ['from' => $artifact['identifier'], 'from_revision' => $artifact['revision'], 'relationship' => $reference['relationship'] ?? 'references', 'to' => $reference['identifier'] ?? $reference['artifact'] ?? null, 'to_revision' => $reference['revision'] ?? null, 'evidence_path' => $artifact['path']];
            }
        }
        usort($relationships, fn (array $left, array $right): int => [$left['from'], $left['relationship'], $left['to']] <=> [$right['from'], $right['relationship'], $right['to']]);
        $glossary = $this->glossary($repositoryRoot, $profiles);
        $repository = ['notice' => 'Generated, non-canonical projection. Rebuild from repository evidence.', 'version' => 1, 'fingerprint' => $manifest->fingerprint, 'business_path' => (string) $manifest->businessPath, 'generated_path' => (string) $manifest->generatedPath, 'counts' => ['profiles' => count($profiles), 'scenarios' => count($scenarios), 'subjects' => count($subjects), 'artifacts' => count($artifacts)], 'evidence' => $manifest->canonicalFiles];

        foreach (['repository' => $repository, 'profiles' => $profiles, 'scenarios' => $scenarios, 'lifecycles' => $lifecycles, 'subjects' => $subjects, 'document-sets' => $documentSets, 'artifact-types' => $artifactTypes, 'artifacts' => $artifacts, 'glossary' => $glossary, 'relationships' => $relationships] as $name => $data) {
            $this->files->put($directory.'/'.$name.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        }

        return ['profiles' => count($profiles), 'scenarios' => count($scenarios), 'lifecycles' => count($lifecycles), 'subjects' => count($subjects), 'document_sets' => count($documentSets), 'artifact_types' => count($artifactTypes), 'artifacts' => count($artifacts), 'relationships' => count($relationships), 'glossary_terms' => count($glossary)];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sorted(array $items): array
    {
        usort($items, fn (array $left, array $right): int => $left['identifier'] <=> $right['identifier']);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     * @return list<array<string, mixed>>
     */
    private function glossary(string $repositoryRoot, array $profiles): array
    {
        $glossary = [];
        foreach ($profiles as $profile) {
            $vocabularyPath = $profile['declarations']['vocabulary'][0] ?? null;
            if (! is_string($vocabularyPath)) {
                continue;
            }
            $path = dirname($repositoryRoot.'/'.$profile['path']).'/'.$vocabularyPath;
            if (! is_file($path)) {
                continue;
            }
            $data = Yaml::parseFile($path);
            foreach (is_array($data) && is_array($data['terms'] ?? null) ? $data['terms'] : [] as $term) {
                if (is_array($term)) {
                    $glossary[] = [...$term, 'profile' => $profile['identifier'], 'evidence_path' => str_replace($repositoryRoot.'/', '', $path)];
                }
            }
        }
        usort($glossary, fn (array $left, array $right): int => $left['term'] <=> $right['term']);

        return $glossary;
    }
}
