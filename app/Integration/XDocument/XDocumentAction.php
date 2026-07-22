<?php

namespace App\Integration\XDocument;

final readonly class XDocumentAction
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $identifier, public string $label, public string $type, public bool $enabled = true, public array $metadata = ['execution_owner' => 'host']) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'label' => $this->label, 'type' => $this->type, 'enabled' => $this->enabled, 'metadata' => $this->metadata];
    }
}
