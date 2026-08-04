<?php

namespace App\Domain\Rostering;

final readonly class ResolvedRosterPolicy
{
    /** @param list<string> $provenance @param array<string, RosterPolicyDefinition> $policies */
    public function __construct(public string $profileIdentifier, public int $revision, public string $generatorName, public string $generatorVersion, public array $provenance, public string $fingerprint, public array $policies = []) {}

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
            if ($policy->status === RosterPolicyStatus::Confirmed) {
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

        return new RosterPolicyCalibrationStatus($confirmed, $provisional, $unresolvedMandatory, $unresolvedQuality, [], $this->fingerprint);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['profile_identifier' => $this->profileIdentifier, 'revision' => $this->revision, 'generator_name' => $this->generatorName, 'generator_version' => $this->generatorVersion, 'policies' => collect($this->policies)->map->toArray()->all(), 'calibration' => $this->calibrationStatus()->toArray(), 'provenance' => $this->provenance, 'fingerprint' => $this->fingerprint];
    }
}
