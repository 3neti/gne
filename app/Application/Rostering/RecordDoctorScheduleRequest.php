<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Models\Doctor;
use App\Models\DoctorScheduleRequest;
use App\Models\RosterPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RecordDoctorScheduleRequest
{
    public function __construct(private RecordRosterAudit $audit) {}

    /** @param list<string> $dates */
    public function handle(User $actor, Doctor $doctor, RosterPeriod $period, DoctorRequestType $type, array $dates, DoctorRequestStatus $status = DoctorRequestStatus::Submitted, ?string $reason = null, ?string $notes = null): DoctorScheduleRequest
    {
        if (! $doctor->active) {
            throw new DomainException('Inactive doctors cannot receive new schedule requests.');
        }
        $dates = collect($dates)->map(fn (string $date): string => CarbonImmutable::parse($date)->toDateString())->unique()->sort()->values();
        if ($dates->isEmpty()) {
            throw new DomainException('A schedule request must contain at least one date.');
        }
        if ($dates->contains(fn (string $date): bool => $date < $period->start_date->toDateString() || $date > $period->end_date->toDateString())) {
            throw new DomainException('Every schedule request date must fall inside the roster period.');
        }
        $duplicate = DoctorScheduleRequest::query()->with('dates')->where('doctor_id', $doctor->id)->where('roster_period_id', $period->id)->where('request_type', $type->value)->where('status', DoctorRequestStatus::Accepted->value)->get()->contains(fn (DoctorScheduleRequest $request): bool => $request->dates->contains(fn ($requestDate): bool => $dates->contains($requestDate->date->toDateString())));
        if ($status === DoctorRequestStatus::Accepted && $duplicate) {
            throw new DomainException('An equivalent effective request already exists.');
        }

        return DB::transaction(function () use ($actor, $doctor, $period, $type, $dates, $status, $reason, $notes): DoctorScheduleRequest {
            $request = DoctorScheduleRequest::query()->create(['identifier' => 'PENDING', 'doctor_id' => $doctor->id, 'roster_period_id' => $period->id, 'request_type' => $type, 'status' => $status, 'starts_on' => $dates->first(), 'ends_on' => $dates->last(), 'reason' => $reason, 'notes' => $notes, 'submitted_at' => now(), 'submitted_by' => $actor->id, 'reviewed_at' => $status === DoctorRequestStatus::Accepted ? now() : null, 'reviewed_by' => $status === DoctorRequestStatus::Accepted ? $actor->id : null]);
            $request->update(['identifier' => sprintf('REQUEST-%06d', $request->id)]);
            $request->dates()->createMany($dates->map(fn (string $date): array => ['date' => $date])->all());
            $payload = $this->auditPayload($request->fresh('dates'));
            $this->audit->handle($actor, 'doctor_request.created', 'doctor_schedule_request', $request->identifier, null, $payload);
            if ($status === DoctorRequestStatus::Accepted) {
                $this->audit->handle($actor, 'doctor_request.accepted', 'doctor_schedule_request', $request->identifier, ['status' => DoctorRequestStatus::Submitted->value], $payload);
            }

            return $request->fresh(['doctor', 'rosterPeriod', 'dates']);
        });
    }

    /** @return array<string, mixed> */
    private function auditPayload(DoctorScheduleRequest $request): array
    {
        return ['request_identifier' => $request->identifier, 'doctor_identifier' => $request->doctor->identifier, 'roster_period_identifier' => $request->rosterPeriod->identifier, 'request_type' => $request->request_type->value, 'status' => $request->status->value, 'dates' => $request->dates->pluck('date')->map->toDateString()->all(), 'reason' => $request->reason, 'notes' => $request->notes];
    }
}
