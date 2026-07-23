<?php

namespace App\Integration\XDocument;

use App\Domain\Compilation\DocumentEvidence;
use App\Domain\Compilation\ResolvedAction;
use App\Domain\Compilation\ResolvedDocument;
use App\Domain\Compilation\ResolvedField;
use App\Domain\Compilation\ResolvedSection;

final readonly class PrepareXDocumentCompilationRequest
{
    public function __construct(
        private NormalizeXDocumentValue $normalizer,
        private ValidateXDocumentCompilationRequest $validator,
        private XDocumentCanonicalJson $canonicalJson,
        private ValidateXDocumentSourceReference $sourceReferences,
    ) {}

    public function handle(ResolvedDocument $document, string $requestedDriver = 'json', bool $includeEvidence = true): XDocumentCompilationRequest
    {
        if ($requestedDriver === '') {
            throw new InvalidXDocumentCompilationRequest('Requested driver must not be empty.');
        }
        $subject = new XDocumentSubject($document->compilationSubject->identifier, $document->compilationSubject->type);
        $sections = array_map(fn (ResolvedSection $section): XDocumentSection => new XDocumentSection($section->identifier, $section->title, array_map(fn (ResolvedField $field): XDocumentField => new XDocumentField($field->identifier, $field->label, $this->normalizer->handle($field->value), $includeEvidence ? [$this->evidence($field->evidence, $subject)] : []), $section->fields)), $document->sections);
        $actions = array_map(fn (ResolvedAction $action): XDocumentAction => new XDocumentAction($action->identifier, $action->label, $action->intent), $document->actions);
        $attachments = array_map(fn (array $attachment): XDocumentAttachment => $this->attachment($attachment), $document->attachments);
        $evidence = $includeEvidence ? array_map(fn (array $item): XDocumentEvidence => new XDocumentEvidence($item['artifact_identifier'], $item['artifact_revision'], $item['artifact_type'], $subject->identifier, $this->sourceReferences->handle($item['source_path'])), $document->evidence) : [];
        $externalDocument = new XDocumentResolvedDocument(
            $document->identifier,
            $document->definitionIdentifier,
            $document->definitionRevision,
            $document->resolutionFingerprint,
            $document->title,
            $document->status,
            $document->audience,
            $subject,
            ['identifier' => $document->primaryArtifact->identifier, 'revision' => $document->primaryArtifact->revision, 'type' => $document->primaryArtifact->type, 'source_reference' => $this->sourceReferences->handle($document->primaryArtifact->sourcePath)],
            $sections,
            $actions,
            $attachments,
            $evidence,
            array_intersect_key($document->metadata, array_flip(['definition_source_fingerprint', 'selected_evidence_count'])),
        );
        $version = new XDocumentContractVersion;
        $capabilities = ['actions', 'attachments'];
        if ($includeEvidence) {
            $capabilities[] = 'evidence';
        }
        $options = ['include_evidence' => $includeEvidence];
        $fingerprint = hash('sha256', $this->canonicalJson->encode(['contract_version' => $version->value, 'document' => $externalDocument->toArray(), 'requested_driver' => $requestedDriver, 'requested_capabilities' => $capabilities, 'options' => $options]));
        $request = new XDocumentCompilationRequest($version->value, 'XDOC-REQUEST@'.$fingerprint, $fingerprint, $document->identifier, $requestedDriver, $capabilities, $options, $externalDocument);
        $this->validator->handle($request);

        return $request;
    }

    private function evidence(DocumentEvidence $evidence, XDocumentSubject $subject): XDocumentEvidence
    {
        return new XDocumentEvidence($evidence->artifactIdentifier, $evidence->artifactRevision, $evidence->artifactType, $subject->identifier, $this->sourceReferences->handle($evidence->sourcePath), $evidence->valuePath);
    }

    /** @param array<string, mixed> $attachment */
    private function attachment(array $attachment): XDocumentAttachment
    {
        $identifier = $attachment['identifier'] ?? null;
        $name = $attachment['name'] ?? null;
        $sourceReference = $attachment['source_reference'] ?? null;
        if (! is_string($identifier) || ! is_string($name) || ($sourceReference !== null && ! is_string($sourceReference))) {
            throw new InvalidXDocumentCompilationRequest('Attachments require identifier and name, and cannot expose absolute filesystem paths.');
        }

        $sourceReference = $sourceReference === null ? null : $this->sourceReferences->handle($sourceReference);

        return new XDocumentAttachment($identifier, $name, is_string($attachment['media_type'] ?? null) ? $attachment['media_type'] : null, is_int($attachment['byte_length'] ?? null) ? $attachment['byte_length'] : null, is_string($attachment['checksum'] ?? null) ? $attachment['checksum'] : null, $sourceReference, is_string($attachment['disposition'] ?? null) ? $attachment['disposition'] : 'attachment');
    }
}
