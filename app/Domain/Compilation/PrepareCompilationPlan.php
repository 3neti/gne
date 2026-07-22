<?php

namespace App\Domain\Compilation;

use App\Domain\Repository\RepositoryManifest;

final readonly class PrepareCompilationPlan
{
    public function __construct(private ResolveDocument $resolver, private BrowserProjectionDriver $browserDriver) {}

    /** @return array<string, mixed> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest, ?string $documentIdentifier = null, ?string $subjectIdentifier = null): array
    {
        $documents = [];
        $subjects = collect($manifest->artifacts)->filter(fn (array $artifact): bool => is_array($artifact['subject'] ?? null))->map(fn (array $artifact): array => $artifact['subject'])->unique('identifier')->sortBy('identifier')->values();

        foreach ($this->resolver->definitions($repositoryRoot, $manifest) as $definition) {
            if ($documentIdentifier !== null && $definition['identifier'] !== $documentIdentifier) {
                continue;
            }
            foreach ($subjects as $subjectData) {
                if ($subjectIdentifier !== null && $subjectData['identifier'] !== $subjectIdentifier) {
                    continue;
                }
                try {
                    $subject = new CompilationSubject($subjectData['identifier'], $subjectData['type']);
                    $resolved = $this->resolver->handle($repositoryRoot, $manifest, new DocumentResolutionRequest($definition['identifier'], $subject));
                    $projection = $this->browserDriver->project($resolved);
                    $documents[] = ['identifier' => $definition['identifier'], 'subject' => $subject->toArray(), 'status' => 'resolved', 'resolved_document' => $resolved->identifier, 'browser_projection' => $projection->identifier];
                } catch (DocumentResolutionException $exception) {
                    $documents[] = ['identifier' => $definition['identifier'], 'subject' => $subjectData, 'status' => 'unresolved', 'reason' => $exception->getMessage()];
                }
            }
        }

        return [
            'status' => 'completed',
            'profiles' => count($manifest->profiles),
            'scenarios' => count($manifest->scenarios),
            'artifacts' => count($manifest->artifacts),
            'compilation_subjects' => $subjects->count(),
            'resolved_documents' => count(array_filter($documents, fn (array $document): bool => $document['status'] === 'resolved')),
            'browser_projections' => count(array_filter($documents, fn (array $document): bool => $document['status'] === 'resolved')),
            'documents' => $documents,
            'drivers' => [
                'browser' => ['available' => true, 'scope' => 'ResolvedDocument projection'],
                'document' => ['available' => false, 'reason' => 'x-document is not installed'],
                'settlement' => ['available' => false, 'reason' => 'x-change is not configured'],
            ],
            'notice' => 'Resolved documents are compiler IR; browser projections are disposable peer outputs.',
        ];
    }
}
