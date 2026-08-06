<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyEnforcementReadiness
{
    /**
     * @param  list<RosterPolicyCoherenceFinding>  $findings
     * @param  list<array<string, mixed>>  $coverage
     */
    public function __construct(public RosterPolicyEnforcementReadinessStatus $status, public string $evaluationDate, public ?string $rosterPeriodIdentifier, public string $effectivePolicyFingerprint, public string $enforcementFingerprint, public int $confirmedPolicyCount, public int $provisionalPolicyCount, public array $findings, public array $coverage, public ?RosterPolicyPeriodFeasibility $periodFeasibility, public string $activationStatus) {}

    public function isReady(): bool
    {
        return $this->status === RosterPolicyEnforcementReadinessStatus::ReadyForEnforcement;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status->value, 'evaluation_date' => $this->evaluationDate, 'roster_period_identifier' => $this->rosterPeriodIdentifier, 'effective_policy_fingerprint' => $this->effectivePolicyFingerprint, 'enforcement_fingerprint' => $this->enforcementFingerprint, 'confirmed_policy_count' => $this->confirmedPolicyCount, 'provisional_policy_count' => $this->provisionalPolicyCount, 'activation_status' => $this->activationStatus, 'conflicts' => $this->findingsByCode('POLICY_REQUIRED_HOURS_CONFLICT'), 'missing_runtime_consumers' => array_values(array_filter($this->coverage, fn (array $entry): bool => ! $entry['enforcement_ready'])), 'unsupported_dependencies' => array_values(array_filter(array_map(fn (RosterPolicyCoherenceFinding $finding): ?array => $finding->code === 'POLICY_UNSUPPORTED_DEPENDENCY' ? $finding->toArray() : null, $this->findings))), 'period_infeasibilities' => $this->periodFeasibility === null ? [] : array_map(fn (RosterPolicyCoherenceFinding $finding): array => $finding->toArray(), $this->periodFeasibility->findings), 'warnings' => array_values(array_map(fn (RosterPolicyCoherenceFinding $finding): array => $finding->toArray(), array_filter($this->findings, fn (RosterPolicyCoherenceFinding $finding): bool => $finding->severity === 'warning'))), 'findings' => array_map(fn (RosterPolicyCoherenceFinding $finding): array => $finding->toArray(), $this->findings), 'coverage' => $this->coverage, 'period_feasibility' => $this->periodFeasibility?->toArray()];
    }

    /** @return list<array<string, mixed>> */
    private function findingsByCode(string $code): array
    {
        return array_values(array_map(fn (RosterPolicyCoherenceFinding $finding): array => $finding->toArray(), array_filter($this->findings, fn (RosterPolicyCoherenceFinding $finding): bool => $finding->code === $code)));
    }
}
