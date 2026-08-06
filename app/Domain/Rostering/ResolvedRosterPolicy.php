<?php

namespace App\Domain\Rostering;

final readonly class ResolvedRosterPolicy
{
    /** @param list<string> $provenance @param array<string, RosterPolicyDefinition> $policies */
    /** @param list<array<string, mixed>> $futurePolicies @param list<array<string, mixed>> $expiredPolicies */
    public function __construct(public string $profileIdentifier, public int $revision, public string $generatorName, public string $generatorVersion, public array $provenance, public string $fingerprint, public array $policies = [], public ?RosterPolicyEvaluationContext $evaluationContext = null, public array $futurePolicies = [], public array $expiredPolicies = []) {}

    public function unspecifiedAvailability(): UnspecifiedAvailabilityPolicy
    {
        return UnspecifiedAvailabilityPolicy::from($this->policies['unspecified_availability']->selectedValue ?? UnspecifiedAvailabilityPolicy::EligibleUnlessBlocked->value);
    }

    public function structuralHoursAllocation(): StructuralHoursAllocationPolicy
    {
        return StructuralHoursAllocationPolicy::from($this->policies['structural_hours_allocation']->selectedValue ?? StructuralHoursAllocationPolicy::EqualPerEligibleDoctor->value);
    }

    public function calibrationStatus(): RosterPolicyCalibrationStatus
    {
        $confirmed = [];
        $provisional = [];
        $unresolvedMandatory = [];
        $unresolvedQuality = [];
        foreach ($this->policies as $key => $policy) {
            if (in_array($policy->status, [RosterPolicyStatus::Confirmed, RosterPolicyStatus::Superseded], true) && $policy->effectiveState === 'current') {
                $confirmed[] = $key;
            } elseif ($policy->status === RosterPolicyStatus::Provisional) {
                $provisional[] = $key;
            } elseif ($policy->status === RosterPolicyStatus::Unconfirmed) {
                if (in_array($key, ['unspecified_availability', 'employment_type_eligibility'], true)) {
                    $unresolvedMandatory[] = $key;
                } else {
                    $unresolvedQuality[] = $key;
                }
            }
        }

        return new RosterPolicyCalibrationStatus($confirmed, $provisional, $unresolvedMandatory, $unresolvedQuality, [], $this->fingerprint, array_values(array_unique(array_column($this->futurePolicies, 'policy_key'))), array_values(array_unique(array_column($this->expiredPolicies, 'policy_key'))), $this->evaluationContext?->evaluationDate->toDateString());
    }

    public function enforcementFingerprint(): string
    {
        $policies = collect($this->policies)->map(fn (RosterPolicyDefinition $policy): array => [
            'key' => $policy->key,
            'revision' => $policy->revision,
            'selected_value' => $policy->selectedValue,
            'configuration' => collect($policy->configuration)->except(['public_holiday_reduces_target', 'holiday_effect'])->sortKeys()->all(),
            'effective_date' => $policy->effectiveDate,
            'effective_until' => $policy->effectiveUntil,
        ])->sortKeys()->all();

        return 'sha256:'.hash('sha256', json_encode($policies, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['profile_identifier' => $this->profileIdentifier, 'revision' => $this->revision, 'generator_name' => $this->generatorName, 'generator_version' => $this->generatorVersion, 'evaluation_context' => $this->evaluationContext?->toArray(), 'policies' => collect($this->policies)->map->toArray()->all(), 'future_policies' => $this->futurePolicies, 'expired_policies' => $this->expiredPolicies, 'calibration' => $this->calibrationStatus()->toArray(), 'provenance' => $this->provenance, 'fingerprint' => $this->fingerprint, 'enforcement_fingerprint' => $this->enforcementFingerprint()];
    }
}
