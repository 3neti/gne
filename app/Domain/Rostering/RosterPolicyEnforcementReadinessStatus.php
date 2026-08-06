<?php

namespace App\Domain\Rostering;

enum RosterPolicyEnforcementReadinessStatus: string
{
    case ReadyForEnforcement = 'ready_for_enforcement';
    case ConfigurationIncomplete = 'configuration_incomplete';
    case PolicyConflict = 'policy_conflict';
    case UnsupportedDependency = 'unsupported_dependency';
    case PeriodInfeasible = 'period_infeasible';
}
