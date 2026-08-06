<?php

namespace App\Domain\Rostering;

use DomainException;

final class InvalidRosterPolicyConfirmation extends DomainException
{
    public function __construct(public readonly RosterPolicyConfirmationValidation $validation)
    {
        parent::__construct(match ($validation->confirmability) {
            RosterPolicyConfirmability::ConfigurationRequired => 'Policy configuration is incomplete: '.implode(', ', $validation->missingParameters).'.',
            RosterPolicyConfirmability::UnsupportedInCurrentRelease => 'The selected policy option is not supported in this release.',
            RosterPolicyConfirmability::Unresolved => 'A decision-pending option cannot be confirmed.',
            default => 'The policy decision is invalid.',
        });
    }
}
