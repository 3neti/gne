<?php

namespace App\Domain\Rostering;

final readonly class FoundationValidationFinding
{
    public function __construct(
        public FoundationValidationSeverity $severity,
        public string $code,
        public string $message,
        public ?string $entityIdentifier = null,
    ) {}

    /** @return array{severity: string, code: string, message: string, entity_identifier: string|null} */
    public function toArray(): array
    {
        return ['severity' => $this->severity->value, 'code' => $this->code, 'message' => $this->message, 'entity_identifier' => $this->entityIdentifier];
    }
}
