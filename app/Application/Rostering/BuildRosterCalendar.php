<?php

namespace App\Application\Rostering;

use App\Models\RosterPeriod;

final readonly class BuildRosterCalendar
{
    public function __construct(private ResolveDoctorAvailability $availability, private ValidateRoster $validate, private BuildDoctorHoursSummary $hours) {}

    /**
     * @return array{
     *   calendar: list<array<string, mixed>>,
     *   weeks: list<array<string, mixed>>,
     *   doctor_matrix: list<array<string, mixed>>,
     *   doctor_hours: list<array<string, mixed>>,
     *   assignments: list<array<string, mixed>>,
     *   validation: array<string, mixed>
     * }
     */
    public function handle(RosterPeriod $period): array
    {
        $period->load(['days', 'assignments.doctor', 'assignments.rosterDay', 'doctorRequirements.doctor', 'scheduleRequests.dates', 'revisions.changes', 'revisions.creator']);
        $availability = $this->availability->handle($period);
        $availabilityByCell = collect($availability['availability'])->keyBy(fn (array $cell): string => $cell['doctor_identifier'].'|'.$cell['date']);
        $validation = $this->validate->handle($period);
        $findingArrays = collect($validation->toArray()['findings']);

        $calendar = array_values($period->days->sortBy('date')->map(function ($day) use ($period, $availability, $findingArrays): array {
            $date = $day->date->toDateString();
            $assignments = $period->assignments->where('roster_day_id', $day->id)->sortBy(fn ($assignment): string => $assignment->doctor->identifier);
            $assigned = $assignments->count();
            $variance = $assigned - $day->required_doctor_count;
            $input = collect($availability['calendar'])->firstWhere('date', $date);

            return ['date' => $date, 'weekday' => $day->date->format('l'), 'day_type' => $day->day_type->value, 'required_count' => $day->required_doctor_count, 'assigned_count' => $assigned, 'variance' => $variance, 'staffing_status' => $variance < 0 ? 'understaffed' : ($variance > 0 ? 'overstaffed' : 'fully_staffed'), 'assigned_doctors' => $assignments->map(fn ($assignment): array => ['assignment_identifier' => $assignment->identifier, 'doctor_identifier' => $assignment->doctor->identifier, 'doctor_name' => $assignment->doctor->full_name, 'credited_hours' => $assignment->credited_hours, 'duty_code' => $assignment->duty_code->value])->values()->all(), 'eligible_count' => $input['eligible_doctor_count'], 'leave_count' => $input['leave_doctor_count'], 'unavailable_count' => $input['unavailable_doctor_count'], 'findings' => $findingArrays->filter(fn (array $finding): bool => ($finding['context']['date'] ?? null) === $date)->values()->all()];
        })->values()->all());

        $doctors = $period->doctorRequirements->filter(fn ($requirement): bool => $requirement->doctor->active)->sortBy(fn ($requirement): string => $requirement->doctor->identifier);
        $matrix = array_values($doctors->map(function ($requirement) use ($period, $availabilityByCell): array {
            $doctor = $requirement->doctor;
            $dates = $period->days->sortBy('date')->map(function ($day) use ($doctor, $period, $availabilityByCell): array {
                $date = $day->date->toDateString();
                $assigned = $period->assignments->first(fn ($assignment): bool => $assignment->doctor_id === $doctor->id && $assignment->roster_day_id === $day->id);
                $cell = $availabilityByCell->get($doctor->identifier.'|'.$date, ['effective_status' => 'unspecified', 'preference' => 'none']);
                $state = $assigned ? '✓' : match ($cell['effective_status']) {
                    'leave' => 'L', 'unavailable' => 'U', default => '–'
                };
                if ($cell['preference'] === 'preferred_work') {
                    $state .= $assigned ? '/PW' : 'PW';
                } elseif ($cell['preference'] === 'preferred_off') {
                    $state .= $assigned ? '/PO!' : 'PO';
                } elseif ($cell['preference'] === 'conflicted') {
                    $state .= '/!';
                }

                return ['date' => $date, 'state' => $state, 'assignment_identifier' => $assigned?->identifier];
            })->values()->all();

            return ['doctor_identifier' => $doctor->identifier, 'doctor_name' => $doctor->full_name, 'dates' => $dates];
        })->values()->all());

        $weeks = array_values(collect($calendar)->chunk(7)->values()->map(fn ($dates, int $index): array => ['week' => $index + 1, 'dates' => array_values($dates->values()->all())])->all());
        $assignments = array_values($period->assignments->sortBy('identifier')->map(fn ($assignment): array => ['identifier' => $assignment->identifier, 'doctor_identifier' => $assignment->doctor->identifier, 'doctor_name' => $assignment->doctor->full_name, 'date' => $assignment->rosterDay->date->toDateString(), 'status' => $assignment->status->value, 'source' => $assignment->source->value, 'duty_code' => $assignment->duty_code->value, 'start_time' => $assignment->start_time, 'end_time' => $assignment->end_time, 'credited_hours' => $assignment->credited_hours, 'notes' => $assignment->notes])->values()->all());

        return ['calendar' => $calendar, 'weeks' => $weeks, 'doctor_matrix' => $matrix, 'doctor_hours' => $this->hours->handle($period), 'assignments' => $assignments, 'validation' => $validation->toArray()];
    }
}
