<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\OperationalRosterPolicySelection;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\RosterPolicyEvaluationContext;

final readonly class SelectOperationalRosterPolicy
{
    public function __construct(private ResolveRosterPolicy $resolve, private ValidateResolvedRosterPolicyCoherence $coherence) {}

    public function handle(RosterPolicyEvaluationContext $context, RosterGenerationInput $input): OperationalRosterPolicySelection
    {
        $confirmed = $this->resolve->handle($context);
        $readiness = $this->coherence->handle($confirmed, $input);
        if ($readiness->isReady()) {
            return new OperationalRosterPolicySelection($confirmed, $confirmed, $readiness, false);
        }
        $fallback = $this->resolve->repositoryFallback($context);

        return new OperationalRosterPolicySelection($confirmed, $fallback, $readiness, true);
    }
}
