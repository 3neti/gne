<?php

namespace App\Domain\Rostering;

final readonly class OperationalRosterPolicySelection
{
    public function __construct(public ResolvedRosterPolicy $confirmedPolicy, public ResolvedRosterPolicy $operationalPolicy, public RosterPolicyEnforcementReadiness $readiness, public bool $usesFallback) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['department_policy_fingerprint' => $this->confirmedPolicy->enforcementFingerprint(), 'operational_policy_fingerprint' => $this->operationalPolicy->enforcementFingerprint(), 'uses_fallback' => $this->usesFallback, 'readiness' => $this->readiness->toArray()];
    }
}
