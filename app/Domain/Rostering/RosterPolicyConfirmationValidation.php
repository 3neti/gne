<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyConfirmationValidation
{
    /** @param list<string> $missingParameters @param array<string, string> $invalidParameters @param list<string> $unsupportedDependencies @param list<string> $effectiveWindowFindings @param array<string, mixed> $configuration */
    public function __construct(public string $policyKey, public string $selectedValue, public RosterPolicyConfirmability $confirmability, public array $missingParameters, public array $invalidParameters, public array $unsupportedDependencies, public array $effectiveWindowFindings, public string $impactSummary, public array $configuration) {}

    public function isConfirmable(): bool
    {
        return $this->confirmability === RosterPolicyConfirmability::Confirmable;
    }

    public function isRecordable(): bool
    {
        return in_array($this->confirmability, [RosterPolicyConfirmability::Confirmable, RosterPolicyConfirmability::UnsupportedInCurrentRelease], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['policy_key' => $this->policyKey, 'selected_value' => $this->selectedValue, 'confirmability' => $this->confirmability->value, 'missing_parameters' => $this->missingParameters, 'invalid_parameters' => $this->invalidParameters, 'unsupported_dependencies' => $this->unsupportedDependencies, 'effective_window_findings' => $this->effectiveWindowFindings, 'impact_summary' => $this->impactSummary, 'configuration' => $this->configuration];
    }
}
