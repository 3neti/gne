<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyOption
{
    /** @param list<RosterPolicyParameterDefinition> $parameters @param list<string> $unsupportedDependencies @param array<string, mixed> $fixedConfiguration */
    public function __construct(public string $value, public string $label, public string $description, public string $impact, public bool $confirmationRequired = true, public bool $supported = true, public array $parameters = [], public array $unsupportedDependencies = [], public array $fixedConfiguration = [], public string $validationImpact = '', public string $qualityImpact = '') {}

    public function confirmability(): RosterPolicyConfirmability
    {
        if (! $this->supported) {
            return RosterPolicyConfirmability::UnsupportedInCurrentRelease;
        }
        if (! $this->confirmationRequired || $this->value === 'unresolved') {
            return RosterPolicyConfirmability::Unresolved;
        }

        return $this->parameters === [] ? RosterPolicyConfirmability::Confirmable : RosterPolicyConfirmability::ConfigurationRequired;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label, 'description' => $this->description, 'impact' => $this->impact, 'generation_impact' => $this->impact, 'validation_impact' => $this->validationImpact ?: $this->impact, 'quality_impact' => $this->qualityImpact ?: $this->impact, 'confirmation_required' => $this->confirmationRequired, 'supported' => $this->supported, 'confirmability' => $this->confirmability()->value, 'parameters' => array_map(fn (RosterPolicyParameterDefinition $parameter): array => $parameter->toArray(), $this->parameters), 'unsupported_dependencies' => $this->unsupportedDependencies, 'fixed_configuration' => $this->fixedConfiguration];
    }
}
