<?php

namespace App\Domain\Rostering;

final class RequiredHoursPolicyCompatibilityMatrix
{
    /** @var array<string, array<string, bool>> */
    private const MATRIX = [
        'roster_period_clinical_duty_target' => ['informational' => true, 'soft_warning' => true, 'hard_minimum' => true, 'hard_maximum' => true],
        'roster_period_minimum_obligation' => ['informational' => true, 'soft_warning' => true, 'hard_minimum' => true, 'hard_maximum' => false],
        'planning_reference_only' => ['informational' => true, 'soft_warning' => true, 'hard_minimum' => false, 'hard_maximum' => false],
    ];

    public function compatible(string $meaning, string $enforcement): bool
    {
        if (! isset(self::MATRIX[$meaning][$enforcement])) {
            throw new \DomainException("Unknown required-hours compatibility combination {$meaning} / {$enforcement}.");
        }

        return self::MATRIX[$meaning][$enforcement];
    }

    /** @return list<array{required_hours_meaning: string, target_hours_enforcement: string, compatible: bool}> */
    public function entries(): array
    {
        $entries = [];
        foreach (self::MATRIX as $meaning => $enforcements) {
            foreach ($enforcements as $enforcement => $compatible) {
                $entries[] = ['required_hours_meaning' => $meaning, 'target_hours_enforcement' => $enforcement, 'compatible' => $compatible];
            }
        }

        return $entries;
    }
}
