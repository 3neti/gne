<?php

namespace App\Application\Rostering;

use App\Models\Doctor;
use App\Models\RosterPeriod;

final readonly class BuildDoctorHoursSummary
{
    /** @return list<array<string, mixed>> */
    public function handle(RosterPeriod $period): array
    {
        $period->loadMissing(['doctorRequirements.doctor', 'assignments.rosterDay']);

        return array_values($period->doctorRequirements
            ->filter(fn ($requirement): bool => $requirement->doctor->active)
            ->sortBy(fn ($requirement): string => $requirement->doctor->identifier)
            ->map(function ($requirement) use ($period): array {
                /** @var Doctor $doctor */
                $doctor = $requirement->doctor;
                $assignments = $period->assignments->where('doctor_id', $doctor->id)->sortBy(fn ($assignment): string => $assignment->rosterDay->date->toDateString());
                $assignedHours = (float) $assignments->sum(fn ($assignment): float => (float) $assignment->credited_hours);
                $requiredHours = (float) $requirement->required_hours;
                $variance = $assignedHours - $requiredHours;

                return ['doctor_identifier' => $doctor->identifier, 'doctor_name' => $doctor->full_name, 'required_hours' => number_format($requiredHours, 2, '.', ''), 'assigned_hours' => number_format($assignedHours, 2, '.', ''), 'variance' => number_format($variance, 2, '.', ''), 'status' => $variance < 0 ? 'below_target' : ($variance > 0 ? 'above_target' : 'on_target'), 'assigned_dates' => $assignments->map(fn ($assignment): string => $assignment->rosterDay->date->toDateString())->values()->all()];
            })
            ->values()
            ->all());
    }
}
