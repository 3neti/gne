<?php

namespace App\Application\Rostering;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateDoctor
{
    public function __construct(private RecordRosterAudit $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, Doctor $doctor, array $attributes, ?string $reason = null): Doctor
    {
        $contractedHours = $attributes['contracted_hours'] ?? null;
        $contractedPeriod = $attributes['contracted_hours_period'] ?? null;
        if ((float) ($attributes['standard_daily_hours'] ?? 0) <= 0 || (($contractedHours === null) !== ($contractedPeriod === null))) {
            throw new InvalidArgumentException('Doctor hours do not satisfy the foundation contract.');
        }

        return DB::transaction(function () use ($actor, $doctor, $attributes, $reason): Doctor {
            $previous = $doctor->toArray();
            $doctor->update($attributes);
            $this->audit->handle($actor, 'doctor.updated', 'doctor', $doctor->identifier, $previous, $doctor->fresh()->toArray(), $reason);

            return $doctor->fresh();
        });
    }
}
