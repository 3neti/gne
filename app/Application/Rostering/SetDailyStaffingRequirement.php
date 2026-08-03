<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\RosterDayType;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SetDailyStaffingRequirement
{
    public function __construct(private RecordRosterAudit $audit) {}

    /** @param array<int|string, int|string> $specificRequirements */
    public function handle(User $actor, RosterPeriod $period, ?int $weekdayDefault, ?int $weekendDefault, array $specificRequirements = []): void
    {
        $values = array_filter([$weekdayDefault, $weekendDefault, ...array_values($specificRequirements)], fn ($value): bool => $value !== null && $value !== '');
        if (array_any($values, fn ($value): bool => (int) $value < 0)) {
            throw new InvalidArgumentException('Staffing requirements cannot be negative.');
        }

        DB::transaction(function () use ($actor, $period, $weekdayDefault, $weekendDefault, $specificRequirements): void {
            $period->days()->get()->each(function (RosterDay $day) use ($actor, $period, $weekdayDefault, $weekendDefault, $specificRequirements): void {
                $specific = $specificRequirements[$day->id] ?? null;
                $bulk = $day->day_type === RosterDayType::Weekend ? $weekendDefault : $weekdayDefault;
                $next = $specific !== null ? (int) $specific : $bulk;
                if ($next === null || $next === $day->required_doctor_count) {
                    return;
                }
                $previous = $day->toArray();
                $day->update(['required_doctor_count' => $next]);
                $this->audit->handle($actor, 'roster_day.staffing_changed', 'roster_day', $period->identifier.'@'.$day->date->toDateString(), $previous, $day->fresh()->toArray());
            });
        });
    }
}
