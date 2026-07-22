<?php

namespace App\Domain\Repository;

final readonly class ValidationFinding
{
    public function __construct(
        public ValidationSeverity $severity,
        public string $code,
        public string $message,
        public ?string $sourcePath = null,
        public ?string $location = null,
        public ?string $remediation = null,
        /** @var array<string, mixed> */
        public array $context = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['severity' => $this->severity->value, 'code' => $this->code, 'message' => $this->message, 'source_path' => $this->sourcePath, 'location' => $this->location, 'remediation' => $this->remediation, 'context' => $this->context];
    }
}
