<?php

namespace App\Domain\Rostering;

final readonly class RosterGenerationFeasibility
{
    /** @param list<string> $assumptions */
    public function __construct(public string $periodIdentifier, public int $requiredAssignmentSlots, public ?string $standardCreditedHoursPerSlot, public string $totalRequiredStaffingHours, public string $totalDoctorTargetHours, public string $structuralHoursVariance, public string $classification, public string $minimumUnavoidableExcess, public string $minimumUnavoidableDeficit, public int $affectedDoctors, public array $assumptions, public string $explanation) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['period_identifier' => $this->periodIdentifier, 'required_assignment_slots' => $this->requiredAssignmentSlots, 'standard_credited_hours_per_slot' => $this->standardCreditedHoursPerSlot, 'total_required_staffing_hours' => $this->totalRequiredStaffingHours, 'total_doctor_target_hours' => $this->totalDoctorTargetHours, 'structural_hours_variance' => $this->structuralHoursVariance, 'classification' => $this->classification, 'minimum_unavoidable_excess' => $this->minimumUnavoidableExcess, 'minimum_unavoidable_deficit' => $this->minimumUnavoidableDeficit, 'affected_doctors' => $this->affectedDoctors, 'assumptions' => $this->assumptions, 'explanation' => $this->explanation];
    }
}
