<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyOption
{
    public function __construct(public string $value, public string $label, public string $description, public string $impact, public bool $confirmationRequired = true) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label, 'description' => $this->description, 'impact' => $this->impact, 'confirmation_required' => $this->confirmationRequired];
    }
}
