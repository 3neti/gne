<?php

namespace App\Domain\Semantics;

use App\Domain\Repository\RepositoryManifest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

final readonly class BuildSemanticIndex
{
    public function __construct(private Filesystem $files) {}

    /** @return array<string, int> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest): array
    {
        $directory = $repositoryRoot.'/'.$manifest->generatedPath.'/semantic';
        $this->files->ensureDirectoryExists($directory);

        $profiles = $this->sorted($manifest->profiles);
        $scenarios = $this->sorted($manifest->scenarios);
        $artifacts = collect($manifest->artifacts)->sortBy([['identifier', 'asc'], ['revision', 'asc']])->values()->all();
        $relationships = collect($artifacts)->flatMap(fn (array $artifact): array => collect(Arr::wrap($artifact['references']))->map(function (mixed $reference) use ($artifact): array {
            $reference = is_array($reference) ? $reference : ['identifier' => $reference];

            return ['from' => $artifact['identifier'], 'from_revision' => $artifact['revision'], 'relationship' => $reference['relationship'] ?? 'references', 'to' => $reference['identifier'] ?? $reference['artifact'] ?? null, 'to_revision' => $reference['revision'] ?? null, 'evidence_path' => $artifact['path']];
        })->all())->sortBy([['from', 'asc'], ['relationship', 'asc'], ['to', 'asc']])->values()->all();
        $glossary = $this->glossary($repositoryRoot, $profiles);
        $repository = ['notice' => 'Generated, non-canonical projection. Rebuild from repository evidence.', 'version' => 1, 'fingerprint' => $manifest->fingerprint, 'business_path' => (string) $manifest->businessPath, 'generated_path' => (string) $manifest->generatedPath, 'counts' => ['profiles' => count($profiles), 'scenarios' => count($scenarios), 'artifacts' => count($artifacts)], 'evidence' => $manifest->canonicalFiles];

        foreach (compact('repository', 'profiles', 'scenarios', 'artifacts', 'glossary', 'relationships') as $name => $data) {
            $this->files->put($directory.'/'.$name.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        }

        return ['profiles' => count($profiles), 'scenarios' => count($scenarios), 'artifacts' => count($artifacts), 'relationships' => count($relationships), 'glossary_terms' => count($glossary)];
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
    private function sorted(array $items): array
    {
        return collect($items)->sortBy('identifier')->values()->all();
    }

    /** @param list<array<string, mixed>> $profiles @return list<array<string, mixed>> */
    private function glossary(string $repositoryRoot, array $profiles): array
    {
        return collect($profiles)->flatMap(function (array $profile) use ($repositoryRoot): array {
            $vocabularyPath = $profile['declarations']['vocabulary'][0] ?? null;
            if (! is_string($vocabularyPath)) {
                return [];
            }
            $path = dirname($repositoryRoot.'/'.$profile['path']).'/'.$vocabularyPath;
            if (! is_file($path)) {
                return [];
            }
            $data = Yaml::parseFile($path);

            return collect($data['terms'] ?? [])->map(fn (array $term): array => [...$term, 'profile' => $profile['identifier'], 'evidence_path' => str_replace($repositoryRoot.'/', '', $path)])->all();
        })->sortBy('term')->values()->all();
    }
}
