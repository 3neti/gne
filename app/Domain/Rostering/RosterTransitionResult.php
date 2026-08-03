<?php

namespace App\Domain\Rostering;

use App\Models\RosterPeriod;

final readonly class RosterTransitionResult
{
    /** @param list<FoundationValidationFinding|DoctorRequestConflict> $findings */
    public function __construct(
        public RosterPeriod $period,
        public RosterPeriodStatus $from,
        public RosterPeriodStatus $to,
        public array $findings,
    ) {}

    /** @return list<FoundationValidationFinding|DoctorRequestConflict> */
    public function warnings(): array
    {
        return array_values(array_filter($this->findings, fn (FoundationValidationFinding|DoctorRequestConflict $finding): bool => $finding->severity === FoundationValidationSeverity::Warning));
    }
}
