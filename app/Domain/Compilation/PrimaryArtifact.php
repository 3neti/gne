<?php

namespace App\Domain\Compilation;

final readonly class PrimaryArtifact
{
    public function __construct(
        public string $identifier,
        public int|string $revision,
        public string $type,
        public string $sourcePath,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'revision' => $this->revision, 'type' => $this->type, 'source_path' => $this->sourcePath];
    }
}
