<?php

namespace App\Integration\XDocument;

final readonly class XDocumentEvidence
{
    public function __construct(
        public string $artifactIdentifier,
        public int|string $artifactRevision,
        public string $artifactType,
        public string $subjectIdentifier,
        public string $sourceReference,
        public ?string $payloadPath = null,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return ['artifact_identifier' => $this->artifactIdentifier, 'artifact_revision' => $this->artifactRevision, 'artifact_type' => $this->artifactType, 'subject_identifier' => $this->subjectIdentifier, 'source_reference' => $this->sourceReference, 'payload_path' => $this->payloadPath];
    }
}
