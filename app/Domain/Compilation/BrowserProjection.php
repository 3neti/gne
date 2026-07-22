<?php

namespace App\Domain\Compilation;

final readonly class BrowserProjection implements DocumentProjection
{
    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  list<array<string, string>>  $actions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public string $identifier, public string $title, public string $status, public array $sections, public array $actions, public array $metadata) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'title' => $this->title, 'status' => $this->status, 'sections' => $this->sections, 'actions' => $this->actions, 'metadata' => $this->metadata];
    }
}
