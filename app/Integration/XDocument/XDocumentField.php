<?php

namespace App\Integration\XDocument;

final readonly class XDocumentField
{
    /**
     * @param  array<string, mixed>  $value
     * @param  list<XDocumentEvidence>  $evidence
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public string $identifier, public string $label, public array $value, public array $evidence, public array $metadata = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'label' => $this->label, 'value' => $this->value, 'evidence' => array_map(fn (XDocumentEvidence $item): array => $item->toArray(), $this->evidence), 'metadata' => $this->metadata === [] ? (object) [] : $this->metadata];
    }
}
