<?php

namespace App\Application\Rostering;

use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRosterPeriod
{
    public function __construct(private RecordRosterAudit $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, RosterPeriod $period, array $attributes): RosterPeriod
    {
        $attributes = array_intersect_key($attributes, array_flip(['title', 'notes']));

        return DB::transaction(function () use ($actor, $period, $attributes): RosterPeriod {
            $previous = $period->toArray();
            $period->update($attributes);
            $this->audit->handle($actor, 'roster_period.updated', 'roster_period', $period->identifier, $previous, $period->fresh()->toArray());

            return $period->fresh();
        });
    }
}
