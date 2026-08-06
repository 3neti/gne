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
        $totalCents = (int) round($variance * 100);
        $ranked = $doctors->map(function (array $doctor) use ($totalCents, $weights, $weightTotal): array {
            $identifier = (string) $doctor['identifier'];
            $exact = $totalCents * ($weights[$identifier] / $weightTotal);
            $whole = $totalCents >= 0 ? (int) floor($exact) : (int) ceil($exact);

            return ['identifier' => $identifier, 'cents' => $whole, 'fraction' => abs($exact - $whole)];
        })->sort(fn (array $left, array $right): int => $right['fraction'] <=> $left['fraction'] ?: $left['identifier'] <=> $right['identifier'])->values();
        $rankedAllocations = $ranked->all();
        $remainingCents = $totalCents - (int) $ranked->sum('cents');
        $direction = $remainingCents <=> 0;
        for ($index = 0; $index < abs($remainingCents); $index++) {
            $rankedAllocations[$index % count($rankedAllocations)]['cents'] += $direction;
        }
        $allocations = collect($rankedAllocations)->sortBy('identifier')->mapWithKeys(fn (array $allocation): array => [$allocation['identifier'] => number_format($allocation['cents'] / 100, 2, '.', '')])->all();

        return new StructuralVarianceAllocationResult('resolved', $allocations, $definition?->identifier ?? 'configured', 'Extra staffing hours were distributed under '.$policy->structuralHoursAllocation()->value.'.');
    }
}
