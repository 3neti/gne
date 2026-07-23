<?php

namespace App\Integration\XDocument;

final readonly class ValidateXDocumentSourceReference
{
    public function handle(string $sourceReference): string
    {
        if ($sourceReference === '' || preg_match('/^(?:\/|\\\\|[A-Za-z]:[\\\\\/]|file:)/i', $sourceReference) === 1) {
            throw new InvalidXDocumentCompilationRequest('x-document source references must be non-empty, opaque references and cannot expose absolute local filesystem paths or file URIs.');
        }

        return $sourceReference;
    }
}
