<?php

namespace App\Application\Rostering;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateDoctor
{
    public function __construct(private RecordRosterAudit $audit) {}

    public function handle(User $actor, Doctor $doctor, ?string $reason = null): Doctor
    {
        return DB::transaction(function () use ($actor, $doctor, $reason): Doctor {
            $previous = $doctor->toArray();
            $doctor->update(['active' => false]);
            $this->audit->handle($actor, 'doctor.deactivated', 'doctor', $doctor->identifier, $previous, $doctor->fresh()->toArray(), $reason);

            return $doctor->fresh();
        });
    }
}
