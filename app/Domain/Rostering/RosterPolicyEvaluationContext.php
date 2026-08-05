<?php

namespace App\Domain\Rostering;

use Carbon\CarbonImmutable;

final readonly class RosterPolicyEvaluationContext
{
    public function __construct(public CarbonImmutable $evaluationDate, public RosterPolicyEvaluationPurpose $purpose, public ?string $rosterPeriodIdentifier = null, public ?string $generationRunIdentifier = null) {}

    public static function current(): self
    {
        return new self(CarbonImmutable::today(), RosterPolicyEvaluationPurpose::CurrentDiagnostics);
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return ['evaluation_date' => $this->evaluationDate->toDateString(), 'purpose' => $this->purpose->value, 'roster_period_identifier' => $this->rosterPeriodIdentifier, 'generation_run_identifier' => $this->generationRunIdentifier];
    }
}
