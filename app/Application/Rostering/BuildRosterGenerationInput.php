<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\RosterGenerationInput;
use App\Models\RosterPeriod;

final readonly class BuildRosterGenerationInput
{
    public function __construct(private ResolveDoctorAvailability $availability) {}

    public function handle(RosterPeriod $period): RosterGenerationInput
    {
        $period->load(['days', 'doctorRequirements.doctor', 'scheduleRequests.dates']);
        $projection = $this->availability->handle($period);
        $requirements = $period->doctorRequirements->keyBy('doctor_id');
        $doctors = $period->doctorRequirements->pluck('doctor')->filter(fn ($doctor): bool => $doctor->active)->sortBy('identifier')->map(fn ($doctor): array => ['identifier' => $doctor->identifier, 'standard_daily_hours' => (string) $doctor->standard_daily_hours, 'required_hours' => (string) ($requirements->get($doctor->id)?->required_hours ?? '0.00')])->values()->all();
        $days = $period->days->sortBy('date')->map(fn ($day): array => ['date' => $day->date->toDateString(), 'required' => $day->required_doctor_count])->values()->all();
        $availability = collect($projection['availability'])->sortBy(fn (array $cell): string => $cell['date'].'|'.$cell['doctor_identifier'])->values()->map(fn (array $cell): array => ['doctor_identifier' => $cell['doctor_identifier'], 'date' => $cell['date'], 'effective_status' => $cell['effective_status'], 'explicit_availability' => $cell['explicit_availability'], 'preference' => $cell['preference']])->all();
        $canonical = ['period_identifier' => $period->identifier, 'status' => $period->status->value, 'days' => $days, 'doctors' => $doctors, 'availability' => $availability, 'existing_assignment_count' => $period->assignments()->count()];

        return new RosterGenerationInput($period->identifier, $period->status->value, $days, $doctors, $availability, $canonical['existing_assignment_count'], 'sha256:'.hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }
}
