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
    public function __construct(private Filesystem $files) {}

    public function handle(string $repositoryRoot): RepositoryManifest
    {
        $findings = [];

        try {
            $configuration = RepositoryConfiguration::load($repositoryRoot);
        } catch (Throwable $exception) {
            return new RepositoryManifest(new RepositoryPath('business'), new RepositoryPath('.gne'), [], [], [], [new ValidationFinding(ValidationSeverity::Error, 'configuration.invalid', $exception->getMessage(), 'gne.yaml')]);
        }

        $businessRoot = $repositoryRoot.'/'.$configuration->businessPath;
        $generatedRoot = $repositoryRoot.'/'.$configuration->generatedPath;

        if ($configuration->businessPath->isWithin($configuration->generatedPath) || $configuration->generatedPath->isWithin($configuration->businessPath)) {
            $findings[] = new ValidationFinding(ValidationSeverity::Error, 'paths.overlap', 'Canonical and generated paths must not overlap.', 'gne.yaml', remediation: 'Choose separate repository.business_path and repository.generated_path values.');
        }

        foreach (['profiles', 'schemas', 'policies', 'workflows', 'lifecycles', 'scenarios', 'documents', 'artifacts', 'decisions', 'analytics'] as $directory) {
            if (! is_dir($businessRoot.'/'.$directory)) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'directory.missing', "Required canonical directory business/{$directory} is missing.", (string) $configuration->businessPath.'/'.$directory);
            }
        }

        [$profiles, $profileFindings] = $this->definitions($businessRoot.'/profiles', 'profile.yaml', 'profile');
        [$scenarios, $scenarioFindings] = $this->definitions($businessRoot.'/profiles', 'scenarios/*.yaml', 'scenario');
        [$artifacts, $artifactFindings] = $this->artifacts($businessRoot);
        $findings = [...$findings, ...$profileFindings, ...$scenarioFindings, ...$artifactFindings];

        foreach ($profiles as $profile) {
            $profileRoot = dirname($repositoryRoot.'/'.$profile['path']);
            foreach (['README.md', 'vocabulary.yaml', 'lifecycles/reservation.yaml', 'scenarios/manual-payment-reservation.yaml', 'policies/payment-review.yaml', 'policies/artifact-immutability.yaml'] as $requiredFile) {
                if (! is_file($profileRoot.'/'.$requiredFile)) {
                    $findings[] = new ValidationFinding(ValidationSeverity::Error, 'profile.baseline_missing', "Profile {$profile['identifier']} requires {$requiredFile}.", $profile['path']);
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
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'source.syntax_invalid', $exception->getMessage(), Str::after($sourceFile->getPathname(), $repositoryRoot.'/'));
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

        return new RepositoryManifest($configuration->businessPath, $configuration->generatedPath, $profiles, $scenarios, $artifacts, $findings);
    }

    /** @return array{list<array<string, mixed>>, list<ValidationFinding>} */
    private function definitions(string $root, string $pattern, string $kind): array
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
                $relativePath = Str::after($path, base_path().'/');
                $definition = ['identifier' => $identifier, 'slug' => (string) ($data['slug'] ?? basename(dirname($path))), 'title' => (string) ($data['title'] ?? $identifier), 'description' => (string) ($data['description'] ?? ''), 'path' => $relativePath, 'profile' => $data['profile'] ?? $identifier, 'lifecycle' => $data['lifecycle'] ?? null, 'relationships' => $data['relationships'] ?? []];
                $definitions[] = $definition;

                if (isset($identifiers[$identifier])) {
                    $findings[] = new ValidationFinding(ValidationSeverity::Error, "{$kind}.duplicate_identifier", "Duplicate {$kind} identifier {$identifier}.", $relativePath);
                }
                $identifiers[$identifier] = true;
            } catch (Throwable $exception) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, "{$kind}.invalid", $exception->getMessage(), Str::after($path, base_path().'/'));
            }
        }

        return [$definitions, $findings];
    }

    /** @return array{list<array<string, mixed>>, list<ValidationFinding>} */
    private function artifacts(string $businessRoot): array
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
                $relativePath = Str::after($path, base_path().'/');
                if (isset($identities[$key])) {
                    $findings[] = new ValidationFinding(ValidationSeverity::Error, 'artifact.duplicate_identifier', "Duplicate artifact identity {$key}.", $relativePath);
                }
                $identities[$key] = true;
                $artifacts[] = ['identifier' => $data['identifier'], 'type' => $data['type'], 'revision' => $data['revision'], 'status' => $data['status'] ?? null, 'profile' => $data['profile'] ?? null, 'scenario' => $data['scenario'] ?? null, 'occurred_at' => $data['occurred_at'] ?? $data['accepted_at'] ?? null, 'references' => Arr::wrap($data['references'] ?? []), 'provenance' => $data['provenance'] ?? [], 'path' => $relativePath];
            } catch (Throwable $exception) {
                $findings[] = new ValidationFinding(ValidationSeverity::Error, 'artifact.invalid', $exception->getMessage(), Str::after($path, base_path().'/'));
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
