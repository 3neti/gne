<?php

namespace App\Domain\Compilation;

final readonly class MissingDocumentEvidence
{
    public function __construct(public string $artifactType, public string $reason) {}

    /** @return array{artifact_type: string, reason: string} */
    public function toArray(): array
    {
        return ['artifact_type' => $this->artifactType, 'reason' => $this->reason];
    }
}
