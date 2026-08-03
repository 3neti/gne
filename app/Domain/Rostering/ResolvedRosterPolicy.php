<?php

namespace App\Domain\Rostering;

final readonly class ResolvedRosterPolicy
{
    /** @param list<string> $provenance */
    public function __construct(public string $profileIdentifier, public int $revision, public string $generatorName, public string $generatorVersion, public array $provenance, public string $fingerprint) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['profile_identifier' => $this->profileIdentifier, 'revision' => $this->revision, 'generator_name' => $this->generatorName, 'generator_version' => $this->generatorVersion, 'rules' => ['active_doctors_only', 'leave_prohibits_assignment', 'unavailability_prohibits_assignment', 'unique_daily_assignment', 'daily_required_headcount', 'required_hour_target', 'preferred_work_first', 'explicit_availability_first', 'under_target_first', 'assignment_count_balance', 'preferred_off_avoidance', 'stable_identifier_tie_break'], 'provenance' => $this->provenance, 'fingerprint' => $this->fingerprint];
    }
}
