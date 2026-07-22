<?php

namespace App\Domain\Compilation;

final readonly class ResolvedDocumentSet
{
    /**
     * @param  list<DocumentInventoryEntry>  $entries
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $identifier,
        public string $fingerprint,
        public CompilationSubject $compilationSubject,
        public string $profile,
        public string $scenario,
        public LifecyclePosition $lifecyclePosition,
        public array $entries,
        public array $metadata = [],
    ) {}

    public function count(DocumentReadiness $readiness): int
    {
        return count(array_filter($this->entries, fn (DocumentInventoryEntry $entry): bool => $entry->readiness === $readiness));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'fingerprint' => $this->fingerprint,
            'compilation_subject' => $this->compilationSubject->toArray(),
            'profile' => $this->profile,
            'scenario' => $this->scenario,
            'lifecycle' => $this->lifecyclePosition->toArray(),
            'counts' => collect(DocumentReadiness::cases())->mapWithKeys(fn (DocumentReadiness $readiness): array => [$readiness->value => $this->count($readiness)])->all(),
            'entries' => array_map(fn (DocumentInventoryEntry $entry): array => $entry->toArray(), $this->entries),
            'metadata' => $this->metadata,
        ];
    }
}
