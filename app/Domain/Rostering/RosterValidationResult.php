<?php

namespace App\Domain\Rostering;

final readonly class RosterValidationResult
{
    /** @param list<RosterValidationFinding> $findings */
    public function __construct(public array $findings) {}

    public function status(): string
    {
        if ($this->errors() !== []) {
            return 'invalid';
        }

        return $this->warnings() === [] ? 'valid' : 'valid_with_warnings';
    }

    /** @return list<RosterValidationFinding> */
    public function errors(): array
    {
        return array_values(array_filter($this->findings, fn (RosterValidationFinding $finding): bool => $finding->severity === FoundationValidationSeverity::Error));
    }

    /** @return list<RosterValidationFinding> */
    public function warnings(): array
    {
        return array_values(array_filter($this->findings, fn (RosterValidationFinding $finding): bool => $finding->severity === FoundationValidationSeverity::Warning));
    }

    /** @return array{status: string, errors: int, warnings: int, findings: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['status' => $this->status(), 'errors' => count($this->errors()), 'warnings' => count($this->warnings()), 'findings' => array_map(fn (RosterValidationFinding $finding): array => $finding->toArray(), $this->findings)];
    }
}
