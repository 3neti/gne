<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\FoundationValidationSeverity;
use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Domain\Rostering\RosterTransitionResult;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class TransitionRosterPeriod
{
    public function __construct(private RosterAuditRecorder $audit, private ValidateRosterFoundation $validate, private ValidateDoctorRequests $validateRequests) {}

    public function handle(User $actor, RosterPeriod $period, RosterPeriodStatus $target, ?string $reason = null): RosterTransitionResult
    {
        if (! in_array($target, $period->status->allowedFoundationTransitions(), true)) {
            throw new InvalidRosterTransition("Cannot transition {$period->status->value} to {$target->value} in the foundation lifecycle.");
        }
        $foundationFindings = $target === RosterPeriodStatus::ReadyForGeneration ? $this->validate->handle($period) : [];
        $requestFindings = $target === RosterPeriodStatus::ReadyForGeneration ? $this->validateRequests->handle($period) : [];
        $findings = [...$foundationFindings, ...$requestFindings];
        $hasErrors = collect($findings)->contains(fn ($finding): bool => $finding->severity === FoundationValidationSeverity::Error);
        if ($hasErrors) {
            throw new InvalidRosterTransition('The roster foundation has validation errors and is not ready for generation.');
        }

        return DB::transaction(function () use ($actor, $period, $target, $reason, $findings): RosterTransitionResult {
            $from = $period->status;
            $previous = $period->toArray();
            $period->update(['status' => $target]);
            $this->audit->record($actor, 'roster_period.transitioned', 'roster_period', $period->identifier, $previous, $period->fresh()->toArray(), $reason);

            return new RosterTransitionResult($period->fresh(), $from, $target, $findings);
        });
    }
}
