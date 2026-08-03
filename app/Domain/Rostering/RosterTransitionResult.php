<?php

namespace App\Domain\Rostering;

use App\Models\RosterPeriod;

final readonly class RosterTransitionResult
{
    /** @param list<FoundationValidationFinding> $findings */
    public function __construct(
        public RosterPeriod $period,
        public RosterPeriodStatus $from,
        public RosterPeriodStatus $to,
        public array $findings,
    ) {}

    /** @return list<FoundationValidationFinding> */
    public function warnings(): array
    {
        return array_values(array_filter($this->findings, fn (FoundationValidationFinding $finding): bool => $finding->severity === FoundationValidationSeverity::Warning));
    }
}
