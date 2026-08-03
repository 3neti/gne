<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Models\Doctor;
use App\Models\DoctorScheduleRequest;
use App\Models\RosterPeriod;
use App\Models\User;

final readonly class RecordAcceptedDoctorScheduleRequest
{
    public function __construct(private RecordDoctorScheduleRequest $record) {}

    /** @param list<string> $dates */
    public function handle(User $actor, Doctor $doctor, RosterPeriod $period, DoctorRequestType $type, array $dates, ?string $reason = null, ?string $notes = null): DoctorScheduleRequest
    {
        return $this->record->handle($actor, $doctor, $period, $type, $dates, DoctorRequestStatus::Accepted, $reason, $notes);
    }
}
