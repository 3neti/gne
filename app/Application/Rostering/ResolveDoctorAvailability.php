<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestConflict;
use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\FoundationValidationSeverity;
use App\Models\Doctor;
use App\Models\DoctorScheduleRequest;
use App\Models\RosterPeriod;
use Illuminate\Support\Collection;

final class ResolveDoctorAvailability
{
    /** @return array<string, mixed> */
    public function handle(RosterPeriod $period): array
    {
        $period->loadMissing(['days', 'doctorRequirements.doctor', 'scheduleRequests.doctor', 'scheduleRequests.dates']);
        $requests = $period->scheduleRequests->sortBy(fn (DoctorScheduleRequest $request): string => $request->doctor->identifier.'|'.$request->starts_on->toDateString().'|'.$request->identifier)->values();
        $doctors = $period->doctorRequirements->pluck('doctor')
            ->merge($requests->pluck('doctor'))
            ->filter(fn (Doctor $doctor): bool => $doctor->active)
            ->unique('id')
            ->sortBy('identifier')
            ->values();
        $effective = $requests->where('status', DoctorRequestStatus::Accepted);
        $availability = [];
        $conflicts = [];
        foreach ($doctors as $doctor) {
            foreach ($period->days as $day) {
                $date = $day->date->toDateString();
                $cellRequests = $effective->filter(fn (DoctorScheduleRequest $request): bool => $request->doctor_id === $doctor->id && $request->dates->contains(fn ($requestDate): bool => $requestDate->date->toDateString() === $date));
                $types = array_values($cellRequests->pluck('request_type')->map(fn (DoctorRequestType $type): string => $type->value)->unique()->values()->all());
                $cellConflicts = $this->conflicts($doctor, $period, $date, $cellRequests, $types);
                array_push($conflicts, ...$cellConflicts);
                $availability[] = ['doctor_identifier' => $doctor->identifier, 'doctor_name' => $doctor->full_name, 'date' => $date, 'effective_status' => $this->effectiveStatus($types), 'blocking' => in_array('leave', $types, true) || in_array('unavailable', $types, true), 'preference' => $this->preference($types), 'request_identifiers' => $cellRequests->pluck('identifier')->sort()->values()->all(), 'request_types' => $types, 'conflict_codes' => array_map(fn (DoctorRequestConflict $conflict): string => $conflict->code, $cellConflicts), 'explanation' => $this->explanation($types)];
            }
        }
        usort($conflicts, fn (DoctorRequestConflict $left, DoctorRequestConflict $right): int => [$left->severity === FoundationValidationSeverity::Error ? 0 : 1, $left->date, $left->doctorIdentifier, $left->code] <=> [$right->severity === FoundationValidationSeverity::Error ? 0 : 1, $right->date, $right->doctorIdentifier, $right->code]);
        $availabilityCollection = collect($availability);
        $calendar = $period->days->map(function ($day) use ($availabilityCollection, $conflicts): array {
            $date = $day->date->toDateString();
            $cells = $availabilityCollection->where('date', $date);

            return ['date' => $date, 'weekday' => $day->date->format('l'), 'day_type' => $day->day_type->value, 'required_doctors' => $day->required_doctor_count, 'available_doctor_count' => $cells->whereIn('effective_status', ['available', 'unspecified'])->count(), 'explicit_available_count' => $cells->where('effective_status', 'available')->count(), 'unavailable_doctor_count' => $cells->where('effective_status', 'unavailable')->count(), 'leave_doctor_count' => $cells->where('effective_status', 'leave')->count(), 'preferred_work_count' => $cells->where('preference', 'preferred_work')->count(), 'preferred_off_count' => $cells->where('preference', 'preferred_off')->count(), 'conflict_count' => collect($conflicts)->where('date', $date)->count()];
        })->values()->all();
        $requirements = $period->doctorRequirements->keyBy('doctor_id');
        $doctorData = $doctors->map(function (Doctor $doctor) use ($availabilityCollection, $requirements): array {
            $cells = $availabilityCollection->where('doctor_identifier', $doctor->identifier);
            $datesFor = fn (string $type): array => $cells->filter(fn (array $cell): bool => in_array($type, $cell['request_types'], true))->pluck('date')->values()->all();

            return ['identifier' => $doctor->identifier, 'name' => $doctor->full_name, 'required_hours' => $requirements->get($doctor->id)?->required_hours, 'standard_daily_hours' => $doctor->standard_daily_hours, 'available_dates' => $datesFor('available'), 'unavailable_dates' => $datesFor('unavailable'), 'leave_dates' => $datesFor('leave'), 'preferred_work_dates' => $datesFor('preferred_work'), 'preferred_off_dates' => $datesFor('preferred_off'), 'conflict_codes' => $cells->pluck('conflict_codes')->flatten()->unique()->values()->all()];
        })->values()->all();

        return ['doctors' => array_values($doctorData), 'calendar' => array_values($calendar), 'availability' => $availability, 'conflicts' => $conflicts, 'requests' => array_values($requests->map(fn (DoctorScheduleRequest $request): array => ['identifier' => $request->identifier, 'doctor_identifier' => $request->doctor->identifier, 'doctor_name' => $request->doctor->full_name, 'request_type' => $request->request_type->value, 'status' => $request->status->value, 'dates' => $request->dates->pluck('date')->map->toDateString()->all(), 'reason' => $request->reason, 'notes' => $request->notes])->all())];
    }

    /**
     * @param  Collection<int, DoctorScheduleRequest>  $requests
     * @param  list<string>  $types
     * @return list<DoctorRequestConflict>
     */
    private function conflicts(Doctor $doctor, RosterPeriod $period, string $date, Collection $requests, array $types): array
    {
        $rules = [
            [['available', 'unavailable'], FoundationValidationSeverity::Error, 'REQUEST_AVAILABLE_AND_UNAVAILABLE', 'Availability and unavailability conflict.', 'Reject or withdraw one hard request.'],
            [['available', 'leave'], FoundationValidationSeverity::Error, 'REQUEST_LEAVE_AND_AVAILABLE', 'Leave conflicts with explicit availability.', 'Reject or withdraw the availability request.'],
            [['leave', 'preferred_work'], FoundationValidationSeverity::Warning, 'REQUEST_LEAVE_AND_PREFERRED_WORK', 'Leave overrides a preferred-work request.', 'Review the soft preference.'],
            [['unavailable', 'preferred_work'], FoundationValidationSeverity::Warning, 'REQUEST_UNAVAILABLE_AND_PREFERRED_WORK', 'Unavailability overrides a preferred-work request.', 'Review the soft preference.'],
            [['preferred_off', 'preferred_work'], FoundationValidationSeverity::Warning, 'REQUEST_PREFERRED_WORK_AND_PREFERRED_OFF', 'Preferred-work and preferred-off requests conflict.', 'Retain only the intended preference.'],
        ];
        $findings = [];
        foreach ($rules as [$required, $severity, $code, $message, $correction]) {
            if (collect($required)->every(fn (string $type): bool => in_array($type, $types, true))) {
                $findings[] = new DoctorRequestConflict($severity, $code, $doctor->identifier, $period->identifier, $date, array_values($requests->pluck('identifier')->sort()->values()->all()), array_values(collect($types)->sort()->values()->all()), $message, $correction);
            }
        }

        return $findings;
    }

    /** @param list<string> $types */
    private function effectiveStatus(array $types): string
    {
        return in_array('leave', $types, true) ? 'leave' : (in_array('unavailable', $types, true) ? 'unavailable' : (in_array('available', $types, true) ? 'available' : 'unspecified'));
    }

    /** @param list<string> $types */
    private function preference(array $types): string
    {
        return in_array('preferred_work', $types, true) && in_array('preferred_off', $types, true) ? 'conflicted' : (in_array('preferred_work', $types, true) ? 'preferred_work' : (in_array('preferred_off', $types, true) ? 'preferred_off' : 'none'));
    }

    /** @param list<string> $types */
    private function explanation(array $types): string
    {
        return $types === [] ? 'No accepted request; eligibility is unspecified.' : 'Effective state derived from accepted request precedence: '.implode(', ', $types).'.';
    }
}
