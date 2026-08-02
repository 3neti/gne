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
        public string $route,
        public string $stage,
        public string $expected,
        public string $action,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
