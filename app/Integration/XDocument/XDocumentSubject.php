<?php

namespace App\Integration\XDocument;

final readonly class XDocumentSubject
{
    public function __construct(public string $identifier, public string $type) {}

    /** @return array{identifier: string, type: string} */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'type' => $this->type];
    }
}
