<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class TransitionRosterPeriod
{
    public function __construct(private RecordRosterAudit $audit, private ValidateRosterFoundation $validate) {}

    public function handle(User $actor, RosterPeriod $period, RosterPeriodStatus $target, ?string $reason = null): RosterPeriod
    {
        if (! in_array($target, $period->status->allowedFoundationTransitions(), true)) {
            throw new InvalidRosterTransition("Cannot transition {$period->status->value} to {$target->value} in the foundation lifecycle.");
        }
        if ($target === RosterPeriodStatus::ReadyForGeneration && $this->validate->handle($period) !== []) {
            throw new InvalidRosterTransition('The roster foundation must have no findings before it is ready for generation.');
        }

        return DB::transaction(function () use ($actor, $period, $target, $reason): RosterPeriod {
            $previous = $period->toArray();
            $period->update(['status' => $target]);
            $this->audit->handle($actor, 'roster_period.transitioned', 'roster_period', $period->identifier, $previous, $period->fresh()->toArray(), $reason);

            return $period->fresh();
        });
    }
}
