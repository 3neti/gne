<?php

namespace App\Domain\Compilation;

use App\Domain\Repository\RepositoryAddress;
use App\Domain\Repository\RepositoryManifest;

final readonly class BuildResolvedDocumentSet
{
    public function __construct(private SelectArtifactChain $chainSelector, private ResolveDocument $resolver) {}

    public function handle(string $repositoryRoot, RepositoryManifest $manifest, CompilationSubject $subject): ResolvedDocumentSet
    {
        $subjectArtifacts = collect($manifest->artifacts)->filter(fn (array $artifact): bool => ($artifact['subject']['identifier'] ?? null) === $subject->identifier);
        if ($subjectArtifacts->isEmpty()) {
            throw new CompilationSubjectNotFound("Compilation subject {$subject->identifier} was not found.");
        }
        $firstArtifact = $subjectArtifacts->first();
        $profile = $firstArtifact['profile'];
        $scenario = $firstArtifact['scenario'];
        $chain = $this->chainSelector->handle($manifest, $subject, $profile, $scenario);
        $definitions = collect($this->resolver->definitions($repositoryRoot, $manifest))
            ->filter(fn (array $definition): bool => $definition['profile'] === $profile && $definition['scenario'] === $scenario)
            ->sortBy('identifier')->values()->all();
        $entries = [];

        foreach ($definitions as $definition) {
            try {
                $resolved = $this->resolver->handle($repositoryRoot, $manifest, new DocumentResolutionRequest($definition['identifier'], $subject));
                $entries[] = new DocumentInventoryEntry($definition['identifier'], $definition['revision'], $definition['title'], $definition['path'], DocumentReadiness::Resolved, $subject, $resolved, [], 'All declared accepted evidence is available.', ['definition_source_fingerprint' => $definition['source_fingerprint']]);
            } catch (DocumentEvidenceNotFound $exception) {
                $missing = new MissingDocumentEvidence($exception->artifactType, $exception->getMessage());
                $entries[] = new DocumentInventoryEntry($definition['identifier'], $definition['revision'], $definition['title'], $definition['path'], DocumentReadiness::Pending, $subject, null, [$missing], "Pending accepted {$exception->artifactType} evidence.", ['definition_source_fingerprint' => $definition['source_fingerprint']]);
            } catch (AmbiguousArtifactSelection|CrossSubjectReferenceViolation $exception) {
                $entries[] = new DocumentInventoryEntry($definition['identifier'], $definition['revision'], $definition['title'], $definition['path'], DocumentReadiness::Unavailable, $subject, null, [], $exception->getMessage(), ['definition_source_fingerprint' => $definition['source_fingerprint']]);
            }
        }

        $lifecycleDefinition = $this->lifecycleDefinition($manifest, $scenario, $subject);
        $lifecycle = $this->lifecyclePosition($chain, $lifecycleDefinition);
        $repository = new RepositoryAddress($repositoryRoot);
        $chainEvidence = array_map(fn (array $artifact): array => ['identifier' => $artifact['identifier'], 'revision' => $artifact['revision'], 'type' => $artifact['type'], 'source_fingerprint' => hash_file('sha256', $repository->absolute($artifact['path']))], $chain->artifacts);
        $fingerprint = hash('sha256', json_encode([
            'subject' => $subject->toArray(),
            'chain' => $chainEvidence,
            'lifecycle' => $lifecycle->toArray(),
            'lifecycle_source_fingerprint' => $lifecycleDefinition['source_fingerprint'],
            'entries' => array_map(fn (DocumentInventoryEntry $entry): array => ['definition' => $entry->definitionIdentifier, 'revision' => $entry->definitionRevision, 'source_fingerprint' => $entry->metadata['definition_source_fingerprint'], 'readiness' => $entry->readiness->value, 'resolved_fingerprint' => $entry->resolvedDocument?->resolutionFingerprint, 'missing_evidence' => array_map(fn (MissingDocumentEvidence $missing): array => $missing->toArray(), $entry->missingEvidence)], $entries),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return new ResolvedDocumentSet('DOCUMENT-SET-'.$subject->identifier.'@'.$fingerprint, $fingerprint, $subject, $profile, $scenario, $lifecycle, $entries, ['selected_artifact_count' => count($chain->artifacts)]);
    }

    /** @return array<string, mixed> */
    private function lifecycleDefinition(RepositoryManifest $manifest, string $scenarioIdentifier, CompilationSubject $subject): array
    {
        $scenario = collect($manifest->scenarios)->firstWhere('identifier', $scenarioIdentifier);
        $lifecycle = collect($manifest->lifecycles)->firstWhere('identifier', $scenario['lifecycle'] ?? null);
        if (! is_array($lifecycle) || $lifecycle['subject_type'] !== $subject->type) {
            throw new DocumentResolutionException("Scenario {$scenarioIdentifier} has no valid lifecycle.");
        }

        return $lifecycle;
    }

    /** @param array<string, mixed> $lifecycle */
    private function lifecyclePosition(SelectedArtifactChain $chain, array $lifecycle): LifecyclePosition
    {
        $acceptedTypes = collect($chain->artifacts)->pluck('type')->flip();
        $stages = [];
        $contiguous = true;
        $current = null;
        $next = null;
        $gaps = [];
        foreach ($lifecycle['transitions'] as $position => $transition) {
            $hasEvidence = $acceptedTypes->has($transition['evidence']);
            if ($contiguous && $hasEvidence) {
                $status = 'completed';
                $current = $transition['to'];
            } elseif ($contiguous) {
                $status = 'pending';
                $next = $transition['to'];
                $contiguous = false;
            } elseif ($hasEvidence) {
                $status = 'gap';
                $gaps[] = $transition['to'];
            } else {
                $status = 'future';
            }
            $stages[] = ['identifier' => $transition['to'], 'artifact_type' => $transition['evidence'], 'position' => $position + 1, 'status' => $status, 'has_evidence' => $hasEvidence];
        }

        return new LifecyclePosition($lifecycle['identifier'], $current, $next, $stages, $gaps);
    }
}
