<?php

namespace App\Domain\Compilation;

final class BrowserProjectionDriver implements DocumentProjectionDriver
{
    public function project(ResolvedDocument $document): BrowserProjection
    {
        return new BrowserProjection(
            $document->identifier,
            $document->title,
            $document->status,
            array_map(fn (ResolvedSection $section): array => $section->toArray(), $document->sections),
            array_map(fn (ResolvedAction $action): array => $action->toArray(), $document->actions),
            ['document_definition' => $document->documentDefinition, 'definition_identifier' => $document->definitionIdentifier, 'definition_revision' => $document->definitionRevision, 'resolution_fingerprint' => $document->resolutionFingerprint, 'primary_artifact' => $document->primaryArtifact->toArray(), 'profile' => $document->profile, 'scenario' => $document->scenario, 'audience' => $document->audience],
        );
    }
}
