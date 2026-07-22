<?php

namespace App\Domain\Compilation;

final readonly class LifecyclePosition
{
    /**
     * @param  list<array<string, mixed>>  $stages
     * @param  list<string>  $gaps
     */
    public function __construct(
        public string $lifecycleIdentifier,
        public ?string $currentStage,
        public ?string $nextStage,
        public array $stages,
        public array $gaps,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['lifecycle_identifier' => $this->lifecycleIdentifier, 'current_stage' => $this->currentStage, 'next_stage' => $this->nextStage, 'stages' => $this->stages, 'gaps' => $this->gaps];
    }
}
