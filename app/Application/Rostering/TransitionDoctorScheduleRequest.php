<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\InvalidDoctorRequestTransition;
use App\Models\DoctorScheduleRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class TransitionDoctorScheduleRequest
{
    public function __construct(private RosterAuditRecorder $audit, private EnsureNoOverlappingEffectiveDoctorRequest $ensureNoOverlap) {}

    public function handle(User $actor, DoctorScheduleRequest $request, DoctorRequestStatus $target, ?string $reason = null): DoctorScheduleRequest
    {
        if (! in_array($target, $request->status->allowedTransitions(), true)) {
            throw new InvalidDoctorRequestTransition("Cannot transition {$request->status->value} to {$target->value}.");
        }
        if ($target === DoctorRequestStatus::Accepted) {
            $this->ensureNoOverlap->handle($request);
        }

        return DB::transaction(function () use ($actor, $request, $target, $reason): DoctorScheduleRequest {
            $oldStatus = $request->status;
            $request->update(['status' => $target, 'reviewed_at' => now(), 'reviewed_by' => $actor->id]);
            $this->audit->record($actor, 'doctor_request.'.$target->value, 'doctor_schedule_request', $request->identifier, ['status' => $oldStatus->value], ['status' => $target->value], $reason, $request->rosterPeriod);

            return $request->fresh(['doctor', 'rosterPeriod', 'dates']);
        });
    }
}
