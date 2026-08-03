<?php

namespace App\Domain\Rostering;

enum RequirementSource: string
{
    case Manual = 'manual';
    case ContractDerived = 'contract_derived';
    case Imported = 'imported';
    case PolicyDerived = 'policy_derived';
}
