<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyCoherenceFinding
{
    /**
     * @param  list<string>  $policyKeys
     * @param  array<string, string>  $selectedValues
     * @param  list<string>  $affectedDates
     * @param  list<string>  $affectedDoctors
     */
    public function __construct(public string $severity, public string $code, public string $message, public array $policyKeys, public array $selectedValues, public ?string $affectedPeriod, public array $affectedDates, public array $affectedDoctors, public string $requiredCorrection) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['severity' => $this->severity, 'code' => $this->code, 'message' => $this->message, 'policy_keys' => $this->policyKeys, 'selected_values' => $this->selectedValues, 'affected_period' => $this->affectedPeriod, 'affected_dates' => $this->affectedDates, 'affected_doctors' => $this->affectedDoctors, 'required_correction' => $this->requiredCorrection];
    }
}
