<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\OverlappingEffectiveDoctorRequest;
use App\Models\DoctorScheduleRequest;

final readonly class EnsureNoOverlappingEffectiveDoctorRequest
{
    public function handle(DoctorScheduleRequest $candidate): void
    {
        $candidate->loadMissing('dates');
        $dates = $candidate->dates->pluck('date')->map->toDateString();
        $overlappingRequests = DoctorScheduleRequest::query()
            ->with('dates')
            ->whereKeyNot($candidate->id)
            ->where('doctor_id', $candidate->doctor_id)
            ->where('roster_period_id', $candidate->roster_period_id)
            ->where('request_type', $candidate->request_type->value)
            ->where('status', DoctorRequestStatus::Accepted->value)
            ->get()
            ->filter(fn (DoctorScheduleRequest $request): bool => $request->dates->contains(fn ($requestDate): bool => $dates->contains($requestDate->date->toDateString())));

        if ($overlappingRequests->isEmpty()) {
            return;
        }

        $overlappingDates = array_values($overlappingRequests
            ->flatMap(fn (DoctorScheduleRequest $request) => $request->dates->pluck('date')->map->toDateString())
            ->intersect($dates)
            ->unique()
            ->sort()
            ->values()
            ->map(fn (mixed $date): string => (string) $date)
            ->all());

        $requestIdentifiers = array_values($overlappingRequests->pluck('identifier')->sort()->values()->map(fn (mixed $identifier): string => (string) $identifier)->all());

        throw new OverlappingEffectiveDoctorRequest($overlappingDates, $requestIdentifiers);
    }
}
