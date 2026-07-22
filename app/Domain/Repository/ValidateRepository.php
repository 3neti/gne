<?php

namespace App\Domain\Repository;

final readonly class ValidateRepository
{
    public function __construct(
        private DiscoverRepository $discoverRepository,
        private ValidateArtifactPayloads $artifactPayloads,
        private ValidateDocumentDefinitions $documentDefinitions,
    ) {}

    public function handle(string $repositoryRoot): RepositoryManifest
    {
        $manifest = $this->discoverRepository->handle($repositoryRoot);
        $findings = [
            ...$manifest->findings,
            ...$this->artifactPayloads->handle($repositoryRoot, $manifest),
            ...$this->documentDefinitions->handle($repositoryRoot, $manifest),
        ];
        $severity = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($findings, fn (ValidationFinding $left, ValidationFinding $right): int => [
            $severity[$left->severity->value], $left->sourcePath ?? '', $left->location ?? '', $left->code, $left->message,
        ] <=> [
            $severity[$right->severity->value], $right->sourcePath ?? '', $right->location ?? '', $right->code, $right->message,
        ]);

        return new RepositoryManifest($manifest->businessPath, $manifest->generatedPath, $manifest->profiles, $manifest->scenarios, $manifest->artifacts, $manifest->fingerprint, $manifest->canonicalFiles, $findings);
    }
}
