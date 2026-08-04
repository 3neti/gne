<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationFeasibility;
use App\Domain\Rostering\RosterGenerationInput;
use App\Domain\Rostering\StructuralHoursAllocationPolicy;
use App\Domain\Rostering\StructuralVarianceAllocationResult;

final readonly class AllocateStructuralVariance
{
    public function handle(RosterGenerationInput $input, RosterGenerationFeasibility $feasibility, ResolvedRosterPolicy $policy): StructuralVarianceAllocationResult
    {
        $variance = round((float) $feasibility->structuralHoursVariance, 2);
        $definition = $policy->policies['structural_hours_allocation'] ?? null;
        if (abs($variance) < 0.005) {
            return new StructuralVarianceAllocationResult('not_required', collect($input->doctors)->mapWithKeys(fn (array $doctor): array => [$doctor['identifier'] => '0.00'])->all(), $definition?->identifier ?? 'not-required', 'Required staffing hours and combined doctor targets are equal.');
        }
        if ($policy->structuralHoursAllocation() === StructuralHoursAllocationPolicy::Unresolved) {
            return new StructuralVarianceAllocationResult('unresolved_policy', [], $definition?->identifier ?? 'unresolved', 'The department has not selected how extra staffing hours should be distributed; individual fairness is not classified.');
        }
        $doctors = collect($input->doctors)->values();
        if ($doctors->isEmpty()) {
            return new StructuralVarianceAllocationResult('unsupported_inputs', [], $definition?->identifier ?? 'unsupported', 'No eligible doctors are available for allocation.');
        }
        $weights = $policy->structuralHoursAllocation() === StructuralHoursAllocationPolicy::EqualPerEligibleDoctor
            ? $doctors->mapWithKeys(fn (array $doctor): array => [$doctor['identifier'] => 1.0])
            : $doctors->mapWithKeys(fn (array $doctor): array => [$doctor['identifier'] => max(0.0, (float) $doctor['required_hours'])]);
        $weightTotal = (float) $weights->sum();
        if ($weightTotal <= 0) {
            return new StructuralVarianceAllocationResult('unsupported_inputs', [], $definition?->identifier ?? 'unsupported', 'Target-hour weights must total more than zero.');
        }
        $remainingCents = (int) round($variance * 100);
        $allocations = [];
        foreach ($doctors as $index => $doctor) {
            $cents = $index === $doctors->count() - 1 ? $remainingCents : (int) round(($variance * 100) * ($weights[$doctor['identifier']] / $weightTotal));
            $allocations[$doctor['identifier']] = number_format($cents / 100, 2, '.', '');
            $remainingCents -= $cents;
        }

        return new StructuralVarianceAllocationResult('resolved', $allocations, $definition?->identifier ?? 'configured', 'Extra staffing hours were distributed under '.$policy->structuralHoursAllocation()->value.'.');
    }
}
