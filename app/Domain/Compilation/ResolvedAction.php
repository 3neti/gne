<?php

namespace App\Domain\Compilation;

final readonly class ResolvedAction
{
    public function __construct(public string $identifier, public string $label, public string $intent) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'label' => $this->label, 'intent' => $this->intent];
    }
}
