<?php

namespace App\Application\Rostering;

use App\Models\DoctorScheduleRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdateDoctorScheduleRequest
{
    public function __construct(private RecordRosterAudit $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, DoctorScheduleRequest $request, array $attributes): DoctorScheduleRequest
    {
        return DB::transaction(function () use ($actor, $request, $attributes): DoctorScheduleRequest {
            $before = Arr::only($request->toArray(), ['reason', 'notes']);
            $request->update(Arr::only($attributes, ['reason', 'notes']));
            $after = Arr::only($request->fresh()->toArray(), ['reason', 'notes']);
            $this->audit->handle($actor, 'doctor_request.updated', 'doctor_schedule_request', $request->identifier, $before, $after);

            return $request->fresh(['doctor', 'rosterPeriod', 'dates']);
        });
    }
}
