<?php

namespace App\Domain\Compilation;

final readonly class ResolvedSection
{
    /** @param list<ResolvedField> $fields */
    public function __construct(public string $identifier, public string $title, public array $fields) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'title' => $this->title, 'fields' => array_map(fn (ResolvedField $field): array => $field->toArray(), $this->fields)];
    }
}
