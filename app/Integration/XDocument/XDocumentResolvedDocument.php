<?php

namespace App\Integration\XDocument;

final readonly class XDocumentResolvedDocument
{
    /**
     * @param  list<string>  $audience
     * @param  list<XDocumentSection>  $sections
     * @param  list<XDocumentAction>  $actions
     * @param  list<XDocumentAttachment>  $attachments
     * @param  list<XDocumentEvidence>  $evidence
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $primaryArtifact
     */
    public function __construct(public string $identifier, public string $definitionIdentifier, public int|string $definitionRevision, public string $resolutionFingerprint, public string $title, public string $status, public array $audience, public XDocumentSubject $subject, public array $primaryArtifact, public array $sections, public array $actions, public array $attachments, public array $evidence, public array $metadata = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'definition_identifier' => $this->definitionIdentifier, 'definition_revision' => $this->definitionRevision, 'resolution_fingerprint' => $this->resolutionFingerprint, 'title' => $this->title, 'status' => $this->status, 'audience' => $this->audience, 'subject' => $this->subject->toArray(), 'primary_artifact' => $this->primaryArtifact, 'sections' => array_map(fn (XDocumentSection $section): array => $section->toArray(), $this->sections), 'actions' => array_map(fn (XDocumentAction $action): array => $action->toArray(), $this->actions), 'attachments' => array_map(fn (XDocumentAttachment $attachment): array => $attachment->toArray(), $this->attachments), 'evidence' => array_map(fn (XDocumentEvidence $item): array => $item->toArray(), $this->evidence), 'metadata' => $this->metadata === [] ? (object) [] : $this->metadata];
    }
}
