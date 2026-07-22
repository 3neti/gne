<?php

namespace App\Integration\XDocument;

final readonly class XDocumentSection
{
    /** @param list<XDocumentField> $fields */
    public function __construct(public string $identifier, public string $title, public array $fields) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'title' => $this->title, 'fields' => array_map(fn (XDocumentField $field): array => $field->toArray(), $this->fields)];
    }
}
