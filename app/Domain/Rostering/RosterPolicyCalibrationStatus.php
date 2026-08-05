<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyCalibrationStatus
{
    /** @param list<string> $confirmed @param list<string> $provisional @param list<string> $unresolvedMandatory @param list<string> $unresolvedQuality @param list<string> $unsupported */
    /** @param list<string> $future @param list<string> $expired */
    public function __construct(public array $confirmed, public array $provisional, public array $unresolvedMandatory, public array $unresolvedQuality, public array $unsupported, public string $fingerprint, public array $future = [], public array $expired = [], public ?string $evaluationDate = null) {}

    public function blocksGeneration(): bool
    {
        return $this->unresolvedMandatory !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['confirmed' => $this->confirmed, 'provisional' => $this->provisional, 'future' => $this->future, 'expired' => $this->expired, 'pending_department_decisions' => count($this->provisional), 'unresolved_mandatory' => $this->unresolvedMandatory, 'unresolved_quality' => $this->unresolvedQuality, 'unsupported' => $this->unsupported, 'blocks_generation' => $this->blocksGeneration(), 'evaluation_date' => $this->evaluationDate, 'effective_policy_fingerprint' => $this->fingerprint];
    }
}
