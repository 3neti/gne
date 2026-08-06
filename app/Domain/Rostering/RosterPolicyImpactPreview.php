<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyImpactPreview
{
    /** @param array<string, mixed> $configuration @param list<string> $affectedDoctors @param list<string> $affectedDates @param list<string> $warnings @param list<string> $limitations */
    public function __construct(public string $policyKey, public string $currentValue, public string $candidateValue, public array $configuration, public string $confirmability, public string $evaluationDate, public array $affectedDoctors, public array $affectedDates, public string $generationImpact, public string $validationImpact, public string $qualityImpact, public string $beforeFingerprint, public string $candidateFingerprint, public array $warnings = [], public array $limitations = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['policy_key' => $this->policyKey, 'current_value' => $this->currentValue, 'candidate_value' => $this->candidateValue, 'configuration' => $this->configuration, 'confirmability' => $this->confirmability, 'evaluation_date' => $this->evaluationDate, 'affected_doctors' => $this->affectedDoctors, 'affected_dates' => $this->affectedDates, 'generation_impact' => $this->generationImpact, 'validation_impact' => $this->validationImpact, 'quality_impact' => $this->qualityImpact, 'before_fingerprint' => $this->beforeFingerprint, 'candidate_fingerprint' => $this->candidateFingerprint, 'warnings' => $this->warnings, 'limitations' => $this->limitations];
    }
}
