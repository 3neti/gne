<?php

namespace App\Domain\Compilation;

use App\Domain\Repository\RepositoryManifest;

final readonly class PrepareCompilationPlan
{
    public function __construct(private BuildResolvedDocumentSet $setBuilder, private BrowserProjectionDriver $browserDriver) {}

    /** @return array<string, mixed> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest, ?string $documentIdentifier = null, ?string $subjectIdentifier = null): array
    {
        $documents = [];
        $subjects = collect($manifest->artifacts)->filter(fn (array $artifact): bool => is_array($artifact['subject'] ?? null))->pluck('subject')->unique('identifier')->sortBy('identifier')->values();
        foreach ($subjects as $subjectData) {
            if ($subjectIdentifier !== null && $subjectData['identifier'] !== $subjectIdentifier) {
                continue;
            }
            $set = $this->setBuilder->handle($repositoryRoot, $manifest, new CompilationSubject($subjectData['identifier'], $subjectData['type']));
            foreach ($set->entries as $entry) {
                if ($documentIdentifier !== null && $entry->definitionIdentifier !== $documentIdentifier) {
                    continue;
                }
                $document = ['identifier' => $entry->definitionIdentifier, 'subject' => $subjectData, 'status' => $entry->readiness->value, 'reason' => $entry->explanation, 'missing_evidence' => array_map(fn (MissingDocumentEvidence $missing): array => $missing->toArray(), $entry->missingEvidence)];
                if ($entry->resolvedDocument !== null) {
                    $projection = $this->browserDriver->project($entry->resolvedDocument);
                    $document = [...$document, 'resolved_document' => $entry->resolvedDocument->identifier, 'browser_projection' => $projection->identifier];
                }
                $documents[] = $document;
            }
        }

        return [
            'status' => 'completed',
            'profiles' => count($manifest->profiles),
            'scenarios' => count($manifest->scenarios),
            'artifacts' => count($manifest->artifacts),
            'compilation_subjects' => $subjects->count(),
            'resolved_documents' => count(array_filter($documents, fn (array $document): bool => $document['status'] === DocumentReadiness::Resolved->value)),
            'pending_documents' => count(array_filter($documents, fn (array $document): bool => $document['status'] === DocumentReadiness::Pending->value)),
            'unavailable_documents' => count(array_filter($documents, fn (array $document): bool => $document['status'] === DocumentReadiness::Unavailable->value)),
            'browser_projections' => count(array_filter($documents, fn (array $document): bool => $document['status'] === DocumentReadiness::Resolved->value)),
            'documents' => $documents,
            'drivers' => [
                'browser' => ['available' => true, 'scope' => 'ResolvedDocument projection'],
                'document' => ['available' => false, 'reason' => 'x-document is not installed'],
                'settlement' => ['available' => false, 'reason' => 'x-change is not configured'],
            ],
            'notice' => 'Document readiness is derived per Compilation Subject; drivers consume resolved IR only.',
        ];
    }
}
