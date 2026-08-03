<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\RequirementSource;
use App\Models\Doctor;
use App\Models\DoctorRosterRequirement;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SetDoctorRosterRequirement
{
    public function __construct(private RosterAuditRecorder $audit) {}

    public function handle(User $actor, RosterPeriod $period, Doctor $doctor, string|int|float $requiredHours, RequirementSource $source = RequirementSource::Manual, ?string $notes = null): DoctorRosterRequirement
    {
        if ((float) $requiredHours < 0) {
            throw new InvalidArgumentException('Required hours cannot be negative.');
        }

        return DB::transaction(function () use ($actor, $period, $doctor, $requiredHours, $source, $notes): DoctorRosterRequirement {
            $requirement = DoctorRosterRequirement::query()->whereBelongsTo($period)->whereBelongsTo($doctor)->first();
            $previous = $requirement?->toArray();
            $requirement = DoctorRosterRequirement::query()->updateOrCreate(
                ['doctor_id' => $doctor->id, 'roster_period_id' => $period->id],
                ['required_hours' => $requiredHours, 'source' => $source, 'notes' => $notes],
            );
            $action = $previous === null ? 'doctor_requirement.created' : 'doctor_requirement.updated';
            $this->audit->record($actor, $action, 'doctor_roster_requirement', $period->identifier.'@'.$doctor->identifier, $previous, $requirement->toArray(), rosterPeriod: $period);

            return $requirement;
        });
    }
}
