<?php

namespace App\Domain\Rostering;

final readonly class PolicyCalibrationScenarioResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(private array $payload) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
