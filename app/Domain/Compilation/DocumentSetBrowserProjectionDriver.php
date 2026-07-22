<?php

namespace App\Domain\Compilation;

final class DocumentSetBrowserProjectionDriver
{
    public function project(ResolvedDocumentSet $set): DocumentSetBrowserProjection
    {
        $data = $set->toArray();
        $entries = array_values(array_map(function (array $entry): array {
            $resolved = $entry['resolved_document'];

            return [
                'definition_identifier' => $entry['definition_identifier'],
                'definition_revision' => $entry['definition_revision'],
                'title' => $entry['title'],
                'readiness' => $entry['readiness'],
                'explanation' => $entry['explanation'],
                'missing_evidence' => $entry['missing_evidence'],
                'resolved_document_identifier' => is_array($resolved) ? $resolved['identifier'] : null,
            ];
        }, $data['entries']));

        return new DocumentSetBrowserProjection($set->identifier, $set->fingerprint, $data['compilation_subject'], $set->profile, $set->scenario, $data['lifecycle'], $data['counts'], $entries);
    }
}
