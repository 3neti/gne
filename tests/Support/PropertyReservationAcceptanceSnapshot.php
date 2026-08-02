<?php

namespace Tests\Support;

final readonly class PropertyReservationAcceptanceSnapshot
{
    /**
     * @param  array<string, int>  $selectedArtifactRevisions
     * @param  list<string>  $resolvedDocuments
     * @param  array<string, list<string>>  $pendingDocuments
     * @param  list<string>  $lifecycleGaps
     */
    public function __construct(
        public string $stage,
        public string $repositoryFingerprint,
        public string $subjectIdentifier,
        public array $selectedArtifactRevisions,
        public ?string $lifecycleStage,
        public ?string $nextLifecycleStage,
        public array $lifecycleGaps,
        public string $documentSetFingerprint,
        public array $resolvedDocuments,
        public array $pendingDocuments,
        public ?string $browserDocumentIdentifier = null,
        public ?string $browserChecksum = null,
        public ?string $browserEtag = null,
    ) {}
}
