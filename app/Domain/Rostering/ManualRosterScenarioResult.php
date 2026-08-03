<?php

namespace App\Domain\Rostering;

final readonly class ManualRosterScenarioResult
{
    /** @param array<string, mixed> $report */
    public function __construct(public array $report, public bool $statePreserved) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...$this->report, 'state_preserved' => $this->statePreserved];
    }
}
