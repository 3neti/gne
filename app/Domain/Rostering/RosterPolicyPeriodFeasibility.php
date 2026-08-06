<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyPeriodFeasibility
{
    /**
     * @param  list<array<string, mixed>>  $dailyEligibility
     * @param  list<string>  $infeasibleDates
     * @param  list<string>  $affectedDoctors
     * @param  list<string>  $unsupportedDependencies
     * @param  list<RosterPolicyCoherenceFinding>  $findings
     */
    public function __construct(public ?string $periodIdentifier, public int $eligibleDoctorDateCells, public array $dailyEligibility, public array $infeasibleDates, public array $affectedDoctors, public float $requiredStaffingHours, public float $combinedTargetHours, public ?float $combinedHoursCap, public int $preferredOffExclusions, public array $unsupportedDependencies, public array $findings, public string $fingerprint) {}

    public function isFeasible(): bool
    {
        return $this->infeasibleDates === [] && ! collect($this->findings)->contains(fn (RosterPolicyCoherenceFinding $finding): bool => $finding->severity === 'error');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['period_identifier' => $this->periodIdentifier, 'eligible_doctor_date_cells' => $this->eligibleDoctorDateCells, 'daily_eligibility' => $this->dailyEligibility, 'infeasible_dates' => $this->infeasibleDates, 'affected_doctors' => $this->affectedDoctors, 'required_staffing_hours' => $this->requiredStaffingHours, 'combined_target_hours' => $this->combinedTargetHours, 'combined_hours_cap' => $this->combinedHoursCap, 'preferred_off_exclusions' => $this->preferredOffExclusions, 'unsupported_dependencies' => $this->unsupportedDependencies, 'findings' => array_map(fn (RosterPolicyCoherenceFinding $finding): array => $finding->toArray(), $this->findings), 'fingerprint' => $this->fingerprint];
    }
}
