<?php

namespace App\Domain\Repository;

final readonly class RepositoryManifest
{
    /** @param list<array<string, mixed>> $profiles @param list<array<string, mixed>> $scenarios @param list<array<string, mixed>> $artifacts @param list<ValidationFinding> $findings */
    public function __construct(
        public RepositoryPath $businessPath,
        public RepositoryPath $generatedPath,
        public array $profiles,
        public array $scenarios,
        public array $artifacts,
        public array $findings = [],
    ) {}

    public function hasErrors(): bool
    {
        return array_any($this->findings, fn (ValidationFinding $finding): bool => $finding->severity === ValidationSeverity::Error);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['business_path' => (string) $this->businessPath, 'generated_path' => (string) $this->generatedPath, 'profiles' => $this->profiles, 'scenarios' => $this->scenarios, 'artifacts' => $this->artifacts, 'findings' => array_map(fn (ValidationFinding $finding): array => $finding->toArray(), $this->findings)];
    }
}
