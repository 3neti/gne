<?php

namespace App\Domain\Rostering;

final readonly class StructuralVarianceAllocationResult
{
    /** @param array<string, string> $allocations */
    public function __construct(public string $status, public array $allocations, public string $policyIdentifier, public string $explanation) {}

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'not_required'], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'allocations' => $this->allocations, 'policy_identifier' => $this->policyIdentifier, 'explanation' => $this->explanation];
    }
}
