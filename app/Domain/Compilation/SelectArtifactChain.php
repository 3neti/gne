<?php

namespace App\Domain\Compilation;

use App\Domain\Repository\RepositoryManifest;

final readonly class SelectArtifactChain
{
    public function handle(RepositoryManifest $manifest, CompilationSubject $subject, string $profile, string $scenario): SelectedArtifactChain
    {
        $subjectArtifacts = array_values(array_filter($manifest->artifacts, fn (array $artifact): bool => ($artifact['subject']['identifier'] ?? null) === $subject->identifier));
        if ($subjectArtifacts === []) {
            throw new CompilationSubjectNotFound("Compilation subject {$subject->identifier} was not found.");
        }
        foreach ($subjectArtifacts as $artifact) {
            if (($artifact['subject']['type'] ?? null) !== $subject->type) {
                throw new DocumentResolutionException("Compilation subject {$subject->identifier} has contradictory subject types.");
            }
            if ($artifact['profile'] !== $profile || $artifact['scenario'] !== $scenario) {
                throw new DocumentResolutionException("Compilation subject {$subject->identifier} is not compatible with {$profile} and {$scenario}.");
            }
        }

        $byIdentity = [];
        foreach ($subjectArtifacts as $artifact) {
            if ($artifact['status'] !== 'accepted') {
                continue;
            }
            $existing = $byIdentity[$artifact['identifier']] ?? null;
            if (! is_array($existing) || $artifact['revision'] > $existing['revision']) {
                $byIdentity[$artifact['identifier']] = $artifact;
            }
        }
        $selected = array_values($byIdentity);
        usort($selected, fn (array $left, array $right): int => [$left['type'], $left['identifier'], $left['revision']] <=> [$right['type'], $right['identifier'], $right['revision']]);
        $allByRevision = collect($manifest->artifacts)->keyBy(fn (array $artifact): string => $artifact['identifier'].'@'.$artifact['revision']);
        foreach ($selected as $artifact) {
            foreach ($artifact['references'] as $reference) {
                if (! is_array($reference) || ! isset($reference['identifier'])) {
                    continue;
                }
                $target = $allByRevision->get($reference['identifier'].'@'.($reference['revision'] ?? 1));
                if (is_array($target) && ($target['subject']['identifier'] ?? null) !== $subject->identifier) {
                    throw new CrossSubjectReferenceViolation("Artifact {$artifact['identifier']} references evidence outside subject {$subject->identifier}.");
                }
            }
        }

        return new SelectedArtifactChain($subject, $profile, $scenario, $selected);
    }
}
