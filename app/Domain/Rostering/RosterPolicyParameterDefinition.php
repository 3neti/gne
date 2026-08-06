<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyParameterDefinition
{
    /** @param list<string> $values */
    public function __construct(public string $key, public string $label, public string $type, public bool $required = true, public ?int $minimum = null, public array $values = [], public string $description = '') {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'type' => $this->type, 'required' => $this->required, 'minimum' => $this->minimum, 'values' => $this->values, 'description' => $this->description];
    }
}
