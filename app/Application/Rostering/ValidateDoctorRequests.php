<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestConflict;
use App\Domain\Rostering\FoundationValidationSeverity;
use App\Models\RosterPeriod;

final readonly class ValidateDoctorRequests
{
    public function __construct(private ResolveDoctorAvailability $resolve) {}

    /** @return list<DoctorRequestConflict> */
    public function handle(RosterPeriod $period): array
    {
        $projection = $this->resolve->handle($period);
        $findings = $projection['conflicts'];
        foreach ($projection['calendar'] as $day) {
            if ($day['staffing_input_status'] !== 'insufficient_eligible_pool') {
                continue;
            }
            $findings[] = new DoctorRequestConflict(FoundationValidationSeverity::Error, 'ELIGIBLE_POOL_BELOW_DAILY_REQUIREMENT', 'ACTIVE-DOCTOR-POOL', $period->identifier, $day['date'], [], [], "Only {$day['eligible_doctor_count']} eligible doctors remain for a requirement of {$day['required_doctor_count']}.", 'Resolve blocking leave or unavailability evidence, add eligible doctors, or reduce the authored staffing requirement.', 'insufficient_eligible_pool');
        }

        return $findings;
    }
}
