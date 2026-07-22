<?php

namespace App\Domain\Compilation;

final readonly class ResolvedField
{
    public function __construct(
        public string $identifier,
        public string $label,
        public mixed $value,
        public DocumentEvidence $evidence,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'label' => $this->label, 'value' => $this->value, 'evidence' => $this->evidence->toArray()];
    }
}
