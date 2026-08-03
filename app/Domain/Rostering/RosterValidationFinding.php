<?php

namespace App\Domain\Rostering;

final readonly class RosterValidationFinding
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public FoundationValidationSeverity $severity,
        public string $code,
        public string $message,
        public array $context = [],
    ) {}

    /** @return array{severity: string, code: string, message: string, context: array<string, mixed>} */
    public function toArray(): array
    {
        return ['severity' => $this->severity->value, 'code' => $this->code, 'message' => $this->message, 'context' => $this->context];
    }
}
