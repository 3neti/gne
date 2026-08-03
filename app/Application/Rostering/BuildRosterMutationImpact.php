<?php

namespace App\Application\Rostering;

use App\Models\RosterPeriod;

final readonly class BuildRosterMutationImpact
{
    public function __construct(private BuildRosterCalendar $calendar) {}

    /**
     * @param  list<string>  $dates
     * @param  list<string>  $doctors
     * @return array<string, mixed>
     */
    public function handle(RosterPeriod $period, array $dates, array $doctors): array
    {
        $projection = $this->calendar->handle($period->fresh());

        return ['affected_dates' => $dates, 'affected_doctors' => $doctors, 'daily_staffing' => collect($projection['calendar'])->whereIn('date', $dates)->values()->all(), 'doctor_hours' => collect($projection['doctor_hours'])->whereIn('doctor_identifier', $doctors)->values()->all()];
    }
}
