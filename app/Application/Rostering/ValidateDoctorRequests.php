<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestConflict;
use App\Models\RosterPeriod;

final readonly class ValidateDoctorRequests
{
    public function __construct(private ResolveDoctorAvailability $resolve) {}

    /** @return list<DoctorRequestConflict> */
    public function handle(RosterPeriod $period): array
    {
        return $this->resolve->handle($period)['conflicts'];
    }
}
