<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\FoundationValidationFinding;
use App\Domain\Rostering\FoundationValidationSeverity;
use App\Models\Doctor;
use App\Models\RosterPeriod;
use Carbon\CarbonImmutable;

final class ValidateRosterFoundation
{
    /** @return list<FoundationValidationFinding> */
    public function handle(RosterPeriod $period): array
    {
        $period->loadMissing(['days', 'doctorRequirements', 'assignments']);
        $findings = [];
        $expectedDates = collect();
        $date = CarbonImmutable::parse($period->start_date);
        while ($date->lte($period->end_date)) {
            $expectedDates->push($date->toDateString());
            $date = $date->addDay();
        }
        $actualDates = $period->days->pluck('date')->map->toDateString();
        foreach ($expectedDates->diff($actualDates) as $missingDate) {
            $findings[] = new FoundationValidationFinding(FoundationValidationSeverity::Error, 'ROSTER_DAY_MISSING', "Roster day {$missingDate} is missing.", $period->identifier);
        }
        foreach ($period->days->where('required_doctor_count', 0) as $day) {
            $findings[] = new FoundationValidationFinding(FoundationValidationSeverity::Warning, 'STAFFING_REQUIREMENT_ZERO', "{$day->date->toDateString()} requires zero doctors.", $period->identifier);
        }
        $doctorIdsWithRequirements = $period->doctorRequirements->pluck('doctor_id');
        Doctor::query()->where('active', true)->whereNotIn('id', $doctorIdsWithRequirements)->orderBy('identifier')->get()->each(function (Doctor $doctor) use (&$findings): void {
            $findings[] = new FoundationValidationFinding(FoundationValidationSeverity::Warning, 'DOCTOR_REQUIREMENT_MISSING', "Active doctor {$doctor->identifier} has no explicit required-hours target.", $doctor->identifier);
        });
        $severityOrder = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($findings, fn (FoundationValidationFinding $left, FoundationValidationFinding $right): int => [$severityOrder[$left->severity->value], $left->entityIdentifier ?? '', $left->code, $left->message] <=> [$severityOrder[$right->severity->value], $right->entityIdentifier ?? '', $right->code, $right->message]);

        return $findings;
    }
}
