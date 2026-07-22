<?php

namespace App\Domain\Compilation;

final readonly class DocumentEvidence
{
    public function __construct(
        public string $artifactIdentifier,
        public int|string $artifactRevision,
        public string $artifactType,
        public string $sourcePath,
        public string $valuePath,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'artifact_identifier' => $this->artifactIdentifier,
            'artifact_revision' => $this->artifactRevision,
            'artifact_type' => $this->artifactType,
            'source_path' => $this->sourcePath,
            'value_path' => $this->valuePath,
        ];
    }
}
