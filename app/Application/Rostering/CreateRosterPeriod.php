<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\RosterDayType;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\RosterPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CreateRosterPeriod
{
    public function __construct(private RosterAuditRecorder $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): RosterPeriod
    {
        $start = CarbonImmutable::parse($attributes['start_date']);
        $end = CarbonImmutable::parse($attributes['end_date']);
        if ($end->lt($start)) {
            throw new InvalidArgumentException('Roster period end date must be on or after its start date.');
        }
        if ((int) $attributes['default_weekday_requirement'] < 0 || (int) $attributes['default_weekend_requirement'] < 0) {
            throw new InvalidArgumentException('Staffing requirements cannot be negative.');
        }

        return DB::transaction(function () use ($actor, $attributes): RosterPeriod {
            $weekdayRequirement = (int) $attributes['default_weekday_requirement'];
            $weekendRequirement = (int) $attributes['default_weekend_requirement'];
            unset($attributes['default_weekday_requirement'], $attributes['default_weekend_requirement']);
            $period = RosterPeriod::query()->create([...$attributes, 'status' => RosterPeriodStatus::Draft, 'created_by' => $actor->id]);
            $date = CarbonImmutable::parse($period->start_date);
            $end = CarbonImmutable::parse($period->end_date);
            while ($date->lte($end)) {
                $weekend = $date->isWeekend();
                $period->days()->create([
                    'date' => $date->toDateString(),
                    'day_type' => $weekend ? RosterDayType::Weekend : RosterDayType::Normal,
                    'required_doctor_count' => $weekend ? $weekendRequirement : $weekdayRequirement,
                ]);
                $date = $date->addDay();
            }
            $this->audit->record($actor, 'roster_period.created', 'roster_period', $period->identifier, null, $period->fresh()->toArray());

            return $period->fresh('days');
        });
    }
}
