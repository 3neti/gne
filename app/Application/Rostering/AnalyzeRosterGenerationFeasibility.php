<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\RosterGenerationFeasibility;
use App\Domain\Rostering\RosterGenerationInput;

final readonly class AnalyzeRosterGenerationFeasibility
{
    public function handle(RosterGenerationInput $input): RosterGenerationFeasibility
    {
        $requiredSlots = array_sum(array_column($input->days, 'required'));
        $dailyHours = collect($input->doctors)->pluck('standard_daily_hours')->map(fn (string $hours): float => (float) $hours)->unique()->values();
        $targetHours = collect($input->doctors)->sum(fn (array $doctor): float => (float) $doctor['required_hours']);

        if ($dailyHours->count() !== 1) {
            return new RosterGenerationFeasibility($input->periodIdentifier, $requiredSlots, null, '0.00', number_format($targetHours, 2, '.', ''), '0.00', 'mixed_or_indeterminate', '0.00', '0.00', count($input->doctors), ['Doctor standard daily hours differ.', 'Mixed duty lengths require candidate-specific feasibility and remain deferred.'], 'Aggregate staffing-demand hours are indeterminate because selected doctors may contribute different credited hours.');
        }

        $standardHours = (float) $dailyHours->first();
        $requiredHours = $requiredSlots * $standardHours;
        $difference = $requiredHours - $targetHours;
        $classification = match (true) {
            $difference > 0 => 'targets_below_staffing_demand',
            $difference < 0 => 'targets_above_staffing_demand',
            default => 'targets_exactly_match_demand',
        };
        $explanation = match ($classification) {
            'targets_below_staffing_demand' => sprintf('The roster requires %s staffing hours, while combined doctor targets total %s hours. At least %s hours must therefore be assigned above target if every staffing requirement is met.', number_format($requiredHours, 2, '.', ','), number_format($targetHours, 2, '.', ','), number_format($difference, 2, '.', ',')),
            'targets_above_staffing_demand' => sprintf('The roster requires %s staffing hours, while combined doctor targets total %s hours. At least %s target hours cannot be assigned without exceeding staffing demand.', number_format($requiredHours, 2, '.', ','), number_format($targetHours, 2, '.', ','), number_format(abs($difference), 2, '.', ',')),
            default => sprintf('Required staffing hours and combined doctor targets both equal %s hours.', number_format($requiredHours, 2, '.', ',')),
        };

        return new RosterGenerationFeasibility($input->periodIdentifier, $requiredSlots, number_format($standardHours, 2, '.', ''), number_format($requiredHours, 2, '.', ''), number_format($targetHours, 2, '.', ''), number_format($difference, 2, '.', ''), $classification, number_format(max(0, $difference), 2, '.', ''), number_format(max(0, -$difference), 2, '.', ''), count($input->doctors), ['All selected assignments use each doctor\'s standard daily credited hours.', 'One primary standard-day assignment per doctor and date.', 'Mixed duties and multiple daily segments are deferred.'], $explanation);
    }
}
