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
    /**
     * @return array{
     *   doctors: list<array<string, mixed>>,
     *   calendar: list<array<string, mixed>>,
     *   weeks: list<array<string, mixed>>,
     *   doctor_availability_matrix: list<array<string, mixed>>,
     *   availability_summary: array<string, int>,
     *   availability: list<array<string, mixed>>,
     *   conflicts: list<DoctorRequestConflict>,
     *   requests: list<array<string, mixed>>
     * }
     */
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
                $effectiveStatus = $this->effectiveStatus($types);
                $hardConflict = collect($cellConflicts)->contains(fn (DoctorRequestConflict $conflict): bool => $conflict->severity === FoundationValidationSeverity::Error);
                $availability[] = ['doctor_identifier' => $doctor->identifier, 'doctor_name' => $doctor->full_name, 'date' => $date, 'effective_status' => $effectiveStatus, 'explicit_availability' => in_array('available', $types, true), 'blocking_status' => $hardConflict ? 'conflicted' : (in_array('leave', $types, true) ? 'leave' : (in_array('unavailable', $types, true) ? 'unavailable' : 'none')), 'preference' => $this->preference($types), 'conflicted' => $cellConflicts !== [], 'request_identifiers' => $cellRequests->pluck('identifier')->sort()->values()->all(), 'request_types' => $types, 'conflict_codes' => array_map(fn (DoctorRequestConflict $conflict): string => $conflict->code, $cellConflicts), 'explanation' => $this->explanation($types)];
            }
        }
        usort($conflicts, fn (DoctorRequestConflict $left, DoctorRequestConflict $right): int => [$left->severity === FoundationValidationSeverity::Error ? 0 : 1, $left->date, $left->doctorIdentifier, $left->code] <=> [$right->severity === FoundationValidationSeverity::Error ? 0 : 1, $right->date, $right->doctorIdentifier, $right->code]);
        $availabilityCollection = collect($availability);
        $calendar = $period->days->map(function ($day) use ($availabilityCollection, $conflicts): array {
            $date = $day->date->toDateString();
            $cells = $availabilityCollection->where('date', $date);

            $eligible = $cells->whereIn('effective_status', ['available', 'unspecified'])->count();
            $conflictCount = collect($conflicts)->where('date', $date)->count();
            $staffingInputStatus = match (true) {
                $eligible < $day->required_doctor_count => 'insufficient_eligible_pool',
                $conflictCount > 0 => 'request_conflict',
                default => 'sufficient_eligible_pool',
            };

            return ['date' => $date, 'weekday' => $day->date->format('l'), 'day_type' => $day->day_type->value, 'required_doctor_count' => $day->required_doctor_count, 'active_doctor_count' => $cells->count(), 'eligible_doctor_count' => $eligible, 'explicit_available_count' => $cells->where('effective_status', 'available')->count(), 'unspecified_doctor_count' => $cells->where('effective_status', 'unspecified')->count(), 'unavailable_doctor_count' => $cells->where('effective_status', 'unavailable')->count(), 'leave_doctor_count' => $cells->where('effective_status', 'leave')->count(), 'preferred_work_count' => $cells->filter(fn (array $cell): bool => in_array($cell['preference'], ['preferred_work', 'conflicted'], true))->count(), 'preferred_off_count' => $cells->filter(fn (array $cell): bool => in_array($cell['preference'], ['preferred_off', 'conflicted'], true))->count(), 'conflict_count' => $conflictCount, 'staffing_input_status' => $staffingInputStatus];
        })->values()->all();
        $requirements = $period->doctorRequirements->keyBy('doctor_id');
        $doctorData = $doctors->map(function (Doctor $doctor) use ($availabilityCollection, $requirements): array {
            $cells = $availabilityCollection->where('doctor_identifier', $doctor->identifier);
            $datesFor = fn (string $type): array => $cells->filter(fn (array $cell): bool => in_array($type, $cell['request_types'], true))->pluck('date')->values()->all();

            return ['identifier' => $doctor->identifier, 'name' => $doctor->full_name, 'required_hours' => $requirements->get($doctor->id)?->required_hours, 'standard_daily_hours' => $doctor->standard_daily_hours, 'explicit_available_dates' => $datesFor('available'), 'unspecified_date_count' => $cells->where('effective_status', 'unspecified')->count(), 'unavailable_dates' => $datesFor('unavailable'), 'leave_dates' => $datesFor('leave'), 'preferred_work_dates' => $datesFor('preferred_work'), 'preferred_off_dates' => $datesFor('preferred_off'), 'conflict_codes' => $cells->pluck('conflict_codes')->flatten()->unique()->values()->all()];
        })->values()->all();

        $weeks = array_values(collect($calendar)->chunk(7)->values()->map(fn (Collection $days, int $index): array => ['week' => $index + 1, 'dates' => array_values($days->values()->all())])->all());
        $matrix = array_values($doctors->map(function (Doctor $doctor) use ($availabilityCollection): array {
            $cells = $availabilityCollection->where('doctor_identifier', $doctor->identifier)->map(fn (array $cell): array => ['date' => $cell['date'], 'state' => $this->matrixState($cell), 'effective_status' => $cell['effective_status'], 'preference' => $cell['preference'], 'conflicted' => $cell['conflicted']])->values()->all();

            return ['doctor_identifier' => $doctor->identifier, 'doctor_name' => $doctor->full_name, 'dates' => $cells];
        })->values()->all());
        $summary = ['explicit_available_total' => $availabilityCollection->where('effective_status', 'available')->count(), 'unspecified_total' => $availabilityCollection->where('effective_status', 'unspecified')->count(), 'unavailable_total' => $availabilityCollection->where('effective_status', 'unavailable')->count(), 'leave_total' => $availabilityCollection->where('effective_status', 'leave')->count()];

        return ['doctors' => array_values($doctorData), 'calendar' => array_values($calendar), 'weeks' => $weeks, 'doctor_availability_matrix' => $matrix, 'availability_summary' => $summary, 'availability' => $availability, 'conflicts' => $conflicts, 'requests' => array_values($requests->map(fn (DoctorScheduleRequest $request): array => ['identifier' => $request->identifier, 'doctor_identifier' => $request->doctor->identifier, 'doctor_name' => $request->doctor->full_name, 'request_type' => $request->request_type->value, 'status' => $request->status->value, 'dates' => $request->dates->pluck('date')->map->toDateString()->all(), 'reason' => $request->reason, 'notes' => $request->notes])->all())];
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
                $findings[] = new DoctorRequestConflict($severity, $code, $doctor->identifier, $period->identifier, $date, array_values($requests->pluck('identifier')->sort()->values()->all()), array_values(collect($types)->sort()->values()->all()), $message, $correction, $this->effectiveStatus($types));
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

    /** @param array<string, mixed> $cell */
    private function matrixState(array $cell): string
    {
        $state = match ($cell['effective_status']) {
            'available' => 'A',
            'unavailable' => 'U',
            'leave' => 'L',
            default => '–',
        };
        if ($cell['preference'] === 'preferred_work') {
            $state = $state === '–' ? 'PW' : $state.'/PW';
        } elseif ($cell['preference'] === 'preferred_off') {
            $state = $state === '–' ? 'PO' : $state.'/PO';
        } elseif ($cell['preference'] === 'conflicted') {
            $state = $state === '–' ? 'PW/PO' : $state.'/PW/PO';
        }

        return $cell['conflicted'] ? $state.'!' : $state;
    }
}
