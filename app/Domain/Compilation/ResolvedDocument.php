<?php

namespace App\Domain\Compilation;

final readonly class ResolvedDocument
{
    /**
     * @param  list<string>  $audience
     * @param  list<ResolvedSection>  $sections
     * @param  list<ResolvedAction>  $actions
     * @param  list<array<string, mixed>>  $attachments
     * @param  list<array<string, mixed>>  $evidence
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $identifier,
        public string $title,
        public string $definitionIdentifier,
        public int|string $definitionRevision,
        public string $documentDefinition,
        public string $resolutionFingerprint,
        public PrimaryArtifact $primaryArtifact,
        public string $profile,
        public string $scenario,
        public string $status,
        public array $audience,
        public array $sections,
        public array $actions,
        public array $attachments,
        public array $evidence,
        public array $metadata,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'title' => $this->title,
            'definition_identifier' => $this->definitionIdentifier,
            'definition_revision' => $this->definitionRevision,
            'document_definition' => $this->documentDefinition,
            'resolution_fingerprint' => $this->resolutionFingerprint,
            'primary_artifact' => $this->primaryArtifact->toArray(),
            'profile' => $this->profile,
            'scenario' => $this->scenario,
            'status' => $this->status,
            'audience' => $this->audience,
            'sections' => array_map(fn (ResolvedSection $section): array => $section->toArray(), $this->sections),
            'actions' => array_map(fn (ResolvedAction $action): array => $action->toArray(), $this->actions),
            'attachments' => $this->attachments,
            'evidence' => $this->evidence,
            'metadata' => $this->metadata,
        ];
    }
}
