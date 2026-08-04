<?php

namespace App\Domain\Rostering;

final readonly class DraftRosterQualityResult
{
    /** @param list<array<string, mixed>> $doctorHours @param list<array<string, mixed>> $weekendDistribution @param list<array<string, mixed>> $consecutivePatterns @param array<string, mixed> $metrics @param array<string, mixed> $availabilityUse @param list<array<string, mixed>> $findings @param list<string> $limitations */
    public function __construct(public string $classification, public array $doctorHours, public array $weekendDistribution, public array $consecutivePatterns, public array $metrics, public array $availabilityUse, public array $findings, public array $limitations) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['classification' => $this->classification, 'doctor_hours' => $this->doctorHours, 'weekend_distribution' => $this->weekendDistribution, 'consecutive_patterns' => $this->consecutivePatterns, 'metrics' => $this->metrics, 'availability_use' => $this->availabilityUse, 'findings' => $this->findings, 'limitations' => $this->limitations];
    }
}
