<?php

namespace App\Domain\Compilation;

final readonly class SelectedArtifactChain
{
    /** @param list<array<string, mixed>> $artifacts */
    public function __construct(public CompilationSubject $subject, public string $profile, public string $scenario, public array $artifacts) {}

    /** @return array<string, mixed> */
    public function one(string $artifactType): array
    {
        $candidates = array_values(array_filter($this->artifacts, fn (array $artifact): bool => $artifact['type'] === $artifactType));
        if ($candidates === []) {
            throw new DocumentEvidenceNotFound($artifactType, "No accepted {$artifactType} artifact is available for subject {$this->subject->identifier}.");
        }
        if (count($candidates) > 1) {
            throw new AmbiguousArtifactSelection("Multiple accepted {$artifactType} artifact identities are available for subject {$this->subject->identifier}.");
        }

        return $candidates[0];
    }
}
