<?php

namespace App\Domain\Repository;

final readonly class RepositoryManifest
{
    /**
     * @param  list<array<string, mixed>>  $profiles
     * @param  list<array<string, mixed>>  $scenarios
     * @param  list<array<string, mixed>>  $artifacts
     * @param  list<string>  $canonicalFiles
     * @param  list<ValidationFinding>  $findings
     * @param  list<array<string, mixed>>  $lifecycles
     */
    public function __construct(
        public RepositoryPath $businessPath,
        public RepositoryPath $generatedPath,
        public array $profiles,
        public array $scenarios,
        public array $artifacts,
        public string $fingerprint = '',
        public array $canonicalFiles = [],
        public array $findings = [],
        public array $lifecycles = [],
    ) {}

    public function hasErrors(): bool
    {
        return array_any($this->findings, fn (ValidationFinding $finding): bool => $finding->severity === ValidationSeverity::Error);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['business_path' => (string) $this->businessPath, 'generated_path' => (string) $this->generatedPath, 'fingerprint' => $this->fingerprint, 'canonical_files' => $this->canonicalFiles, 'profiles' => $this->profiles, 'scenarios' => $this->scenarios, 'lifecycles' => $this->lifecycles, 'artifacts' => $this->artifacts, 'findings' => array_map(fn (ValidationFinding $finding): array => $finding->toArray(), $this->findings)];
    }
}
