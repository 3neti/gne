<?php

namespace App\Domain\Compilation;

use App\Domain\Repository\RepositoryAddress;
use App\Domain\Repository\RepositoryManifest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

final readonly class ResolveDocument
{
    public function __construct(private Filesystem $files) {}

    public function handle(string $repositoryRoot, RepositoryManifest $manifest, string $documentIdentifier): ResolvedDocument
    {
        $definition = collect($this->definitions($repositoryRoot, $manifest))->firstWhere('identifier', $documentIdentifier);

        if (! is_array($definition)) {
            throw new DocumentResolutionException("Document definition {$documentIdentifier} was not found.");
        }

        $primaryArtifactType = $definition['primary_artifact_type'] ?? null;
        $artifactTypes = $definition['artifacts'] ?? [];

        if (! is_string($primaryArtifactType) || ! is_array($artifactTypes) || ! in_array($primaryArtifactType, $artifactTypes, true)) {
            throw new DocumentResolutionException("Document definition {$documentIdentifier} must declare a primary artifact included in artifacts.");
        }

        $selectedArtifacts = [];
        foreach ($artifactTypes as $artifactType) {
            if (! is_string($artifactType)) {
                throw new DocumentResolutionException("Document definition {$documentIdentifier} contains an invalid artifact type.");
            }
            $selectedArtifacts[$artifactType] = $this->selectArtifact($manifest, $definition, $artifactType);
        }

        $primaryArtifact = $selectedArtifacts[$primaryArtifactType];
        $sections = [];
        foreach ($definition['sections'] ?? [] as $sectionDefinition) {
            $fields = [];
            foreach ($sectionDefinition['fields'] ?? [] as $fieldDefinition) {
                $fields[] = $this->resolveField($fieldDefinition, $selectedArtifacts, $documentIdentifier);
            }
            $sections[] = new ResolvedSection($sectionDefinition['identifier'], $sectionDefinition['title'], $fields);
        }

        $actions = array_map(
            fn (array $action): ResolvedAction => new ResolvedAction($action['identifier'], $action['label'], $action['intent']),
            $definition['actions'] ?? [],
        );
        $evidence = collect($selectedArtifacts)->map(fn (array $artifact): array => [
            'artifact_identifier' => $artifact['identifier'],
            'artifact_revision' => $artifact['revision'],
            'artifact_type' => $artifact['type'],
            'source_path' => $artifact['path'],
        ])->sortBy([['artifact_type', 'asc'], ['artifact_identifier', 'asc']])->values()->all();

        return new ResolvedDocument(
            'RESOLVED-'.$definition['identifier'].'-'.$primaryArtifact['identifier'].'-R'.$primaryArtifact['revision'],
            $definition['title'],
            $definition['path'],
            $primaryArtifact['revision'],
            $definition['profile'],
            $definition['scenario'],
            $primaryArtifact['status'],
            array_values($definition['audience'] ?? []),
            $sections,
            $actions,
            array_values($definition['attachments'] ?? []),
            $evidence,
            ['definition_identifier' => $definition['identifier'], 'definition_revision' => $definition['revision'], 'repository_fingerprint' => $manifest->fingerprint],
        );
    }

    /** @return list<array<string, mixed>> */
    public function definitions(string $repositoryRoot, RepositoryManifest $manifest): array
    {
        $repository = new RepositoryAddress($repositoryRoot);
        $definitions = [];

        foreach ($manifest->profiles as $profile) {
            $profileRoot = dirname($repository->absolute($profile['path']));
            foreach ($profile['declarations']['documents'] ?? [] as $documentPath) {
                $absolutePath = $profileRoot.'/'.$documentPath;
                $definition = Yaml::parseFile($absolutePath);
                if (! is_array($definition) || ! is_string($definition['identifier'] ?? null)) {
                    throw new DocumentResolutionException("Invalid document definition: {$documentPath}");
                }
                $definitions[] = [...$definition, 'profile' => $profile['identifier'], 'path' => $repository->relative($absolutePath)];
            }
        }

        usort($definitions, fn (array $left, array $right): int => $left['identifier'] <=> $right['identifier']);

        return $definitions;
    }

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    private function selectArtifact(RepositoryManifest $manifest, array $definition, string $artifactType): array
    {
        $artifacts = array_values(array_filter($manifest->artifacts, fn (array $artifact): bool => $artifact['type'] === $artifactType
            && $artifact['profile'] === $definition['profile']
            && $artifact['scenario'] === $definition['scenario']
            && $artifact['status'] === 'accepted'));

        usort($artifacts, function (array $left, array $right): int {
            $identifierOrder = $left['identifier'] <=> $right['identifier'];

            return $identifierOrder !== 0 ? $identifierOrder : $right['revision'] <=> $left['revision'];
        });

        if ($artifacts === []) {
            throw new DocumentResolutionException("No accepted {$artifactType} artifact is available for {$definition['identifier']}.");
        }

        return $artifacts[0];
    }

    /** @param array<string, mixed> $fieldDefinition @param array<string, array<string, mixed>> $selectedArtifacts */
    private function resolveField(array $fieldDefinition, array $selectedArtifacts, string $documentIdentifier): ResolvedField
    {
        $source = $fieldDefinition['source'] ?? [];
        $artifactType = $source['artifact'] ?? null;
        $valuePath = $source['path'] ?? null;
        $artifact = is_string($artifactType) ? ($selectedArtifacts[$artifactType] ?? null) : null;

        if (! is_array($artifact) || ! is_string($valuePath) || ! Arr::has($artifact, $valuePath)) {
            throw new DocumentResolutionException("Field {$fieldDefinition['identifier']} in {$documentIdentifier} has unresolved evidence.");
        }

        return new ResolvedField(
            $fieldDefinition['identifier'],
            $fieldDefinition['label'],
            Arr::get($artifact, $valuePath),
            new DocumentEvidence($artifact['identifier'], $artifact['revision'], $artifact['type'], $artifact['path'], $valuePath),
        );
    }
}
