<?php

namespace App\Domain\Rostering;

final readonly class RosterLifecycleScenarioResult
{
    /** @param list<array<string, mixed>> $steps */
    public function __construct(public string $scenario, public bool $passed, public array $steps, public bool $statePreserved) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['scenario' => $this->scenario, 'passed' => $this->passed, 'state_preserved' => $this->statePreserved, 'steps' => $this->steps];
    }
}
