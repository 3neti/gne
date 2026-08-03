<?php

namespace App\Domain\Rostering;

use App\Models\RosterAssignment;
use App\Models\RosterRevision;

final readonly class RosterMutationResult
{
    /** @param array<string, mixed> $impact */
    public function __construct(
        public string $operation,
        public bool $preview,
        public ?RosterAssignment $assignment,
        public ?RosterRevision $revision,
        public RosterValidationResult $validation,
        public array $impact,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['operation' => $this->operation, 'preview' => $this->preview, 'assignment_identifier' => $this->assignment?->identifier, 'revision_number' => $this->revision?->revision_number, 'validation' => $this->validation->toArray(), 'impact' => $this->impact];
    }
}
