<?php

namespace App\Domain\Rostering;

final readonly class DoctorRequestConflict
{
    /**
     * @param  list<string>  $requestIdentifiers
     * @param  list<string>  $requestTypes
     */
    public function __construct(public FoundationValidationSeverity $severity, public string $code, public string $doctorIdentifier, public string $rosterPeriodIdentifier, public string $date, public array $requestIdentifiers, public array $requestTypes, public string $message, public ?string $suggestedCorrection = null, public ?string $effectiveState = null) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['severity' => $this->severity->value, 'code' => $this->code, 'doctor_identifier' => $this->doctorIdentifier, 'roster_period_identifier' => $this->rosterPeriodIdentifier, 'date' => $this->date, 'request_identifiers' => $this->requestIdentifiers, 'request_types' => $this->requestTypes, 'effective_state' => $this->effectiveState, 'message' => $this->message, 'suggested_correction' => $this->suggestedCorrection];
    }
}
