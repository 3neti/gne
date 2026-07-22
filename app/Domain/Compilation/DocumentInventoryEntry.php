<?php

namespace App\Domain\Compilation;

final readonly class DocumentInventoryEntry
{
    /**
     * @param  list<MissingDocumentEvidence>  $missingEvidence
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $definitionIdentifier,
        public int|string $definitionRevision,
        public string $title,
        public string $definitionPath,
        public DocumentReadiness $readiness,
        public CompilationSubject $compilationSubject,
        public ?ResolvedDocument $resolvedDocument,
        public array $missingEvidence,
        public string $explanation,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'definition_identifier' => $this->definitionIdentifier,
            'definition_revision' => $this->definitionRevision,
            'title' => $this->title,
            'definition_path' => $this->definitionPath,
            'readiness' => $this->readiness->value,
            'compilation_subject' => $this->compilationSubject->toArray(),
            'resolved_document' => $this->resolvedDocument?->toArray(),
            'missing_evidence' => array_map(fn (MissingDocumentEvidence $evidence): array => $evidence->toArray(), $this->missingEvidence),
            'explanation' => $this->explanation,
            'metadata' => $this->metadata,
        ];
    }
}
