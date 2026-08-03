<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RegisterDoctor
{
    public function __construct(private RosterAuditRecorder $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): Doctor
    {
        $contractedHours = $attributes['contracted_hours'] ?? null;
        $contractedPeriod = $attributes['contracted_hours_period'] ?? null;
        if ((float) ($attributes['standard_daily_hours'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Standard daily hours must be positive.');
        }
        if (($contractedHours === null) !== ($contractedPeriod === null)) {
            throw new InvalidArgumentException('Contracted hours require an explicit contracted-hours period.');
        }

        return DB::transaction(function () use ($actor, $attributes): Doctor {
            $doctor = Doctor::query()->create([...$attributes, 'identifier' => 'PENDING-'.Str::ulid()]);
            $doctor->update(['identifier' => sprintf('DOCTOR-%06d', $doctor->id)]);
            $this->audit->record($actor, 'doctor.created', 'doctor', $doctor->identifier, null, $doctor->fresh()->toArray());

            return $doctor->fresh();
        });
    }
}
