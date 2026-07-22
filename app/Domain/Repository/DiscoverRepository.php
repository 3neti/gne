<?php

namespace App\Domain\Repository;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final readonly class DiscoverRepository
{
    public function __construct(private Filesystem $files, private CanonicalRepositoryFingerprint $fingerprint) {}

    public function handle(string $repositoryRoot): RepositoryManifest
    {
        $findings = [];

        try {
            $repository = new RepositoryAddress($repositoryRoot);
        } catch (Throwable $exception) {
            return new RepositoryManifest(new RepositoryPath('business'), new RepositoryPath('.gne'), [], [], [], findings: [new ValidationFinding(ValidationSeverity::Error, 'repository.invalid', $exception->getMessage())]);
        }

        try {
            $configuration = RepositoryConfiguration::load($repository->root);
        } catch (Throwable $exception) {
            return new RepositoryManifest(new RepositoryPath('business'), new RepositoryPath('.gne'), [], [], [], findings: [new ValidationFinding(ValidationSeverity::Error, 'configuration.invalid', $exception->getMessage(), 'gne.yaml')]);
        }

        $businessRoot = $repository->absolute($configuration->businessPath);
        $generatedRoot = $repository->absolute($configuration->generatedPath);

        if (! is_file($repository->absolute('GENEI.md'))) {
            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'repository.self_description_missing', 'GENEI.md is required as the repository self-description.', 'GENEI.md');
        }

        if ($configuration->businessPath->isWithin($configuration->generatedPath) || $configuration->generatedPath->isWithin($configuration->businessPath)) {
            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'paths.overlap', 'Canonical and generated paths must not overlap.', 'gne.yaml', remediation: 'Choose separate repository.business_path and repository.generated_path values.');
        }

        foreach (['profiles', 'schemas', 'policies', 'workflows', 'lifecycles', 'scenarios', 'documents', 'artifacts', 'decisions', 'analytics'] as $directory) {
            if (! is_dir($businessRoot.'/'.$directory)) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'directory.missing', "Required canonical directory business/{$directory} is missing.", (string) $configuration->businessPath.'/'.$directory);
            }
        }

        [$profiles, $profileFindings] = $this->definitions($repository, $businessRoot.'/profiles', 'profile.yaml', 'profile');
        [$scenarios, $scenarioFindings] = $this->definitions($repository, $businessRoot.'/profiles', 'scenarios/*.yaml', 'scenario');
        [$artifacts, $artifactFindings] = $this->artifacts($repository, $businessRoot);
        $findings = [...$findings, ...$profileFindings, ...$scenarioFindings, ...$artifactFindings];

        foreach ($profiles as $profile) {
            $profileRoot = dirname($repository->absolute($profile['path']));
            foreach ($profile['declarations'] as $group => $declaredPaths) {
                foreach ($declaredPaths as $declaredPath) {
                    try {
                        $safePath = new RepositoryPath($declaredPath);
                        if (! is_file($profileRoot.'/'.$safePath)) {
                            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'profile.declaration_missing', "Profile {$profile['identifier']} declares missing {$group} file {$declaredPath}.", $profile['path']);
                        }
                    } catch (Throwable $exception) {
                        $findings[] = new ValidationFinding(ValidationSeverity::Error, 'profile.declaration_unsafe', $exception->getMessage(), $profile['path']);
                    }
                }
            }
        }

        $artifactIdentities = collect($artifacts)->mapWithKeys(fn (array $artifact): array => [$artifact['identifier'].'@'.$artifact['revision'] => true]);
        foreach ($artifacts as $artifact) {
            foreach ($artifact['references'] as $reference) {
                if (is_array($reference) && isset($reference['identifier']) && ! $artifactIdentities->has($reference['identifier'].'@'.($reference['revision'] ?? 1))) {
                    $findings[] = new ValidationFinding(ValidationSeverity::Error, 'artifact.reference_missing', "Artifact reference {$reference['identifier']}@".($reference['revision'] ?? 1).' does not exist.', $artifact['path']);
                }
            }
        }

        foreach ($this->files->allFiles($businessRoot) as $sourceFile) {
            try {
                if (in_array($sourceFile->getExtension(), ['yaml', 'yml'], true)) {
                    Yaml::parseFile($sourceFile->getPathname());
                } elseif ($sourceFile->getExtension() === 'json') {
                    json_decode($sourceFile->getContents(), true, flags: JSON_THROW_ON_ERROR);
                }
            } catch (Throwable $exception) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'source.syntax_invalid', $exception->getMessage(), $repository->relative($sourceFile->getPathname()));
            }
        }

        foreach ($configuration->enabledProfiles as $enabledProfile) {
            if (! array_any($profiles, fn (array $profile): bool => $profile['slug'] === $enabledProfile || $profile['identifier'] === $enabledProfile)) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'profile.enabled_missing', "Enabled profile {$enabledProfile} was not discovered.", 'gne.yaml');
            }
        }

        if (is_dir($generatedRoot) && $this->containsCanonicalMarkers($generatedRoot)) {
            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'generated.contains_canonical', 'Generated state contains files marked canonical.', (string) $configuration->generatedPath);
        }

        $canonicalEvidence = $this->fingerprint->build($repository, $configuration->businessPath);

        return new RepositoryManifest($configuration->businessPath, $configuration->generatedPath, $profiles, $scenarios, $artifacts, $canonicalEvidence['fingerprint'], $canonicalEvidence['files'], $findings);
    }

    /** @return array{list<array<string, mixed>>, list<ValidationFinding>} */
    private function definitions(RepositoryAddress $repository, string $root, string $pattern, string $kind): array
    {
        if (! is_dir($root)) {
            return [[], []];
        }

        $paths = $pattern === 'profile.yaml'
            ? $this->files->glob($root.'/*/'.$pattern)
            : $this->files->glob($root.'/*/'.$pattern);
        sort($paths);
        $definitions = [];
        $findings = [];
        $identifiers = [];

        foreach ($paths as $path) {
            try {
                $data = Yaml::parseFile($path);
                if (! is_array($data) || ! is_string($data['identifier'] ?? null)) {
                    throw new ParseException("The {$kind} definition requires an identifier.");
                }

                $identifier = $data['identifier'];
                $relativePath = $repository->relative($path);
                $declarations = [];
                if ($kind === 'profile') {
                    foreach (['vocabulary', 'lifecycles', 'scenarios', 'policies', 'documents', 'schemas'] as $declaration) {
                        $declaredPaths = Arr::wrap($data[$declaration] ?? []);
                        if ($declaredPaths === [] || array_any($declaredPaths, fn (mixed $declaredPath): bool => ! is_string($declaredPath))) {
                            throw new ParseException("The profile definition requires a {$declaration} file declaration.");
                        }
                        $declarations[$declaration] = array_values($declaredPaths);
                    }
                }
                $definition = ['identifier' => $identifier, 'slug' => (string) ($data['slug'] ?? basename(dirname($path))), 'title' => (string) ($data['title'] ?? $identifier), 'description' => (string) ($data['description'] ?? ''), 'path' => $relativePath, 'profile' => $data['profile'] ?? $identifier, 'lifecycle' => $data['lifecycle'] ?? null, 'relationships' => $data['relationships'] ?? [], 'declarations' => $declarations];
                $definitions[] = $definition;

                if (isset($identifiers[$identifier])) {
                    $findings[] = new ValidationFinding(ValidationSeverity::Error, "{$kind}.duplicate_identifier", "Duplicate {$kind} identifier {$identifier}.", $relativePath);
                }
                $identifiers[$identifier] = true;
            } catch (Throwable $exception) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, "{$kind}.invalid", $exception->getMessage(), $repository->relative($path));
            }
        }

        return [$definitions, $findings];
    }

    /** @return array{list<array<string, mixed>>, list<ValidationFinding>} */
    private function artifacts(RepositoryAddress $repository, string $businessRoot): array
    {
        $paths = $this->files->glob($businessRoot.'/profiles/*/examples/artifacts/*.{json,yaml,yml}', GLOB_BRACE);
        sort($paths);
        $artifacts = [];
        $findings = [];
        $identities = [];

        foreach ($paths as $path) {
            try {
                $data = Str::endsWith($path, '.json') ? json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR) : Yaml::parseFile($path);
                if (! is_array($data) || ! is_string($data['identifier'] ?? null) || ! isset($data['revision'], $data['type'])) {
                    throw new ParseException('Artifact requires identifier, type, and revision.');
                }
                $key = $data['identifier'].'@'.$data['revision'];
                $relativePath = $repository->relative($path);
                if (isset($identities[$key])) {
                    $findings[] = new ValidationFinding(ValidationSeverity::Error, 'artifact.duplicate_identifier', "Duplicate artifact identity {$key}.", $relativePath);
                }
                $identities[$key] = true;
                $artifacts[] = ['identifier' => $data['identifier'], 'type' => $data['type'], 'revision' => $data['revision'], 'status' => $data['status'] ?? null, 'profile' => $data['profile'] ?? null, 'scenario' => $data['scenario'] ?? null, 'occurred_at' => $data['occurred_at'] ?? $data['accepted_at'] ?? null, 'references' => Arr::wrap($data['references'] ?? []), 'payload' => $data['payload'] ?? [], 'provenance' => $data['provenance'] ?? [], 'path' => $relativePath];
            } catch (Throwable $exception) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'artifact.invalid', $exception->getMessage(), $repository->relative($path));
            }
        }

        return [$artifacts, $findings];
    }

    private function containsCanonicalMarkers(string $generatedRoot): bool
    {
        foreach ($this->files->allFiles($generatedRoot) as $file) {
            if (str_contains($file->getContents(), 'canonical: true')) {
                return true;
            }
        }

        return false;
    }
}
