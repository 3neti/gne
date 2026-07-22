<?php

namespace App\Domain\Compilation;

use App\Domain\Repository\RepositoryManifest;

final readonly class PrepareCompilationPlan
{
    public function __construct(private ResolveDocument $resolver, private BrowserProjectionDriver $browserDriver) {}

    /** @return array<string, mixed> */
    public function handle(string $repositoryRoot, RepositoryManifest $manifest): array
    {
        $documents = [];

        foreach ($this->resolver->definitions($repositoryRoot, $manifest) as $definition) {
            try {
                $resolved = $this->resolver->handle($repositoryRoot, $manifest, $definition['identifier']);
                $projection = $this->browserDriver->project($resolved);
                $documents[] = ['identifier' => $definition['identifier'], 'status' => 'resolved', 'resolved_document' => $resolved->identifier, 'browser_projection' => $projection->identifier];
            } catch (DocumentResolutionException $exception) {
                $documents[] = ['identifier' => $definition['identifier'], 'status' => 'unresolved', 'reason' => $exception->getMessage()];
            }
        }

        return [
            'status' => 'completed',
            'profiles' => count($manifest->profiles),
            'scenarios' => count($manifest->scenarios),
            'artifacts' => count($manifest->artifacts),
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
