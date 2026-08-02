<?php

namespace App\Domain\Storyboard;

final readonly class StoryboardFrame
{
    public function __construct(
        public int $sequence,
        public string $identifier,
        public string $act,
        public string $title,
        public string $persona,
        public string $displayRoute,
        public string $stage,
        public string $expected,
        public string $action,
        public string $captureType,
        public string $captureRoute,
        public bool $requiresAuthentication,
        public string $expectedFinalRoute,
        public bool $productionSurface,
    ) {}

    /** @return array<string, bool|int|string> */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'identifier' => $this->identifier,
            'act' => $this->act,
            'title' => $this->title,
            'persona' => $this->persona,
            'display_route' => $this->displayRoute,
            'stage' => $this->stage,
            'expected' => $this->expected,
            'action' => $this->action,
            'capture_type' => $this->captureType,
            'capture_route' => $this->captureRoute,
            'requires_authentication' => $this->requiresAuthentication,
            'expected_final_route' => $this->expectedFinalRoute,
            'production_surface' => $this->productionSurface,
        ];
    }
}
