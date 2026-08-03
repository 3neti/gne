<?php

namespace App\Domain\Rostering;

final readonly class RosterGenerationInput
{
    /** @param list<array<string, mixed>> $days @param list<array<string, mixed>> $doctors @param list<array<string, mixed>> $availability */
    public function __construct(public string $periodIdentifier, public string $status, public array $days, public array $doctors, public array $availability, public int $existingAssignmentCount, public string $fingerprint) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['period_identifier' => $this->periodIdentifier, 'status' => $this->status, 'days' => $this->days, 'doctors' => $this->doctors, 'availability' => $this->availability, 'existing_assignment_count' => $this->existingAssignmentCount, 'fingerprint' => $this->fingerprint];
    }
}
