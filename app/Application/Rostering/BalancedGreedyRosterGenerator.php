<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterGenerator;
use App\Domain\Rostering\GeneratedRosterResult;
use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterGenerationInput;

final class BalancedGreedyRosterGenerator implements RosterGenerator
{
    public function generate(RosterGenerationInput $input, ResolvedRosterPolicy $policy): GeneratedRosterResult
    {
        $cells = collect($input->availability)->keyBy(fn (array $cell): string => $cell['doctor_identifier'].'|'.$cell['date']);
        $totals = collect($input->doctors)->mapWithKeys(fn (array $doctor): array => [$doctor['identifier'] => ['hours' => 0.0, 'count' => 0]])->all();
        $assignments = [];
        $staffing = [];
        $findings = [];

        foreach ($input->days as $day) {
            $selected = [];
            $candidates = collect();
            for ($slot = 1; $slot <= $day['required']; $slot++) {
                $candidates = collect($input->doctors)->reject(function (array $doctor) use ($cells, $day, $selected): bool {
                    $cell = $cells->get($doctor['identifier'].'|'.$day['date']);

                    return in_array($doctor['identifier'], $selected, true) || in_array($cell['effective_status'] ?? null, ['leave', 'unavailable'], true) || (float) $doctor['standard_daily_hours'] <= 0;
                })->sort(function (array $left, array $right) use ($cells, $day, $totals): int {
                    return $this->rank($left, $cells->get($left['identifier'].'|'.$day['date']), $totals[$left['identifier']]) <=> $this->rank($right, $cells->get($right['identifier'].'|'.$day['date']), $totals[$right['identifier']]);
                })->values();
                $doctor = $candidates->first();
                if ($doctor === null) {
                    break;
                }
                $cell = $cells->get($doctor['identifier'].'|'.$day['date']);
                $before = $totals[$doctor['identifier']];
                $selected[] = $doctor['identifier'];
                $totals[$doctor['identifier']]['hours'] += (float) $doctor['standard_daily_hours'];
                $totals[$doctor['identifier']]['count']++;
                $assignments[] = ['doctor_identifier' => $doctor['identifier'], 'date' => $day['date'], 'duty_code' => 'standard_day', 'credited_hours' => number_format((float) $doctor['standard_daily_hours'], 2, '.', ''), 'source' => 'generated', 'explanation' => ['reason_codes' => array_values(array_filter(['ACTIVE_DOCTOR', 'ELIGIBLE_ON_DATE', 'DAILY_SLOT_REQUIRED', ((float) $doctor['required_hours'] - $before['hours']) > 0 ? 'BELOW_REQUIRED_HOURS' : null, $before['count'] === min(array_column($totals, 'count')) ? 'LOWER_ASSIGNMENT_COUNT' : null, ($cell['preference'] ?? null) === 'preferred_work' ? 'PREFERRED_WORK' : null, ($cell['explicit_availability'] ?? false) ? 'EXPLICIT_AVAILABLE' : null, 'STABLE_TIE_BREAK'])), 'remaining_required_hours_before' => number_format((float) $doctor['required_hours'] - $before['hours'], 2, '.', ''), 'assignment_count_before' => $before['count'], 'availability' => $cell['effective_status'] ?? 'unspecified', 'preference' => $cell['preference'] ?? 'none', 'ranking_position' => 1]];
            }
            $assigned = count($selected);
            $staffing[] = ['date' => $day['date'], 'required' => $day['required'], 'assigned' => $assigned, 'missing_slots' => max(0, $day['required'] - $assigned), 'eligible_doctor_count' => $assigned + max(0, $candidates->count() - 1), 'status' => $assigned === $day['required'] ? 'fully_staffed' : 'understaffed'];
            if ($assigned < $day['required']) {
                $findings[] = ['severity' => 'error', 'code' => 'ROSTER_DAY_UNDERSTAFFED', 'date' => $day['date'], 'message' => 'No further eligible doctor was available.', 'missing_slots' => $day['required'] - $assigned];
            }
        }

        $hours = collect($input->doctors)->map(function (array $doctor) use ($totals): array {
            $assigned = $totals[$doctor['identifier']]['hours'];
            $target = (float) $doctor['required_hours'];

            return ['doctor_identifier' => $doctor['identifier'], 'required_hours' => number_format($target, 2, '.', ''), 'assigned_hours' => number_format($assigned, 2, '.', ''), 'variance' => number_format($assigned - $target, 2, '.', ''), 'assignment_count' => $totals[$doctor['identifier']]['count'], 'status' => $assigned < $target ? 'below_target' : ($assigned > $target ? 'above_target' : 'on_target')];
        })->all();
        foreach ($hours as $doctor) {
            if ($doctor['status'] !== 'on_target') {
                $findings[] = ['severity' => 'warning', 'code' => $doctor['status'] === 'below_target' ? 'DOCTOR_HOURS_BELOW_TARGET' : 'DOCTOR_HOURS_ABOVE_TARGET', 'doctor_identifier' => $doctor['doctor_identifier'], 'message' => 'Generated assigned hours differ from the required-hours target.'];
            }
        }
        $assignedKeys = collect($assignments)->mapWithKeys(fn (array $assignment): array => [$assignment['doctor_identifier'].'|'.$assignment['date'] => true]);
        $preferences = collect($input->availability)->filter(fn (array $cell): bool => in_array($cell['preference'], ['preferred_work', 'preferred_off'], true))->map(function (array $cell) use ($assignedKeys): array {
            $assigned = $assignedKeys->has($cell['doctor_identifier'].'|'.$cell['date']);
            $honored = $cell['preference'] === 'preferred_work' ? $assigned : ! $assigned;

            return ['doctor_identifier' => $cell['doctor_identifier'], 'date' => $cell['date'], 'type' => $cell['preference'], 'honored' => $honored, 'assigned' => $assigned, 'explanation' => $honored ? 'The generated assignment outcome honored the accepted preference.' : ($cell['preference'] === 'preferred_work' ? 'The date was filled by higher-ranked eligible candidates.' : 'Staffing and balancing required this eligible doctor.')];
        })->values()->all();
        foreach ($preferences as $preference) {
            if (! $preference['honored']) {
                $findings[] = ['severity' => 'warning', 'code' => $preference['type'] === 'preferred_off' ? 'ASSIGNMENT_PREFERRED_OFF' : 'PREFERRED_WORK_UNHONORED', 'doctor_identifier' => $preference['doctor_identifier'], 'date' => $preference['date'], 'message' => $preference['explanation']];
            }
        }
        usort($findings, fn (array $left, array $right): int => [$left['severity'] === 'error' ? 0 : 1, $left['date'] ?? '', $left['doctor_identifier'] ?? '', $left['code']] <=> [$right['severity'] === 'error' ? 0 : 1, $right['date'] ?? '', $right['doctor_identifier'] ?? '', $right['code']]);
        $payload = ['generator' => $policy->generatorName, 'version' => $policy->generatorVersion, 'policy_fingerprint' => $policy->fingerprint, 'input_fingerprint' => $input->fingerprint, 'assignments' => $assignments, 'staffing' => $staffing, 'hours' => $hours, 'preferences' => $preferences, 'findings' => $findings];
        $status = collect($findings)->contains(fn (array $finding): bool => $finding['severity'] === 'error') ? 'invalid' : ($findings === [] ? 'valid' : 'valid_with_warnings');

        return new GeneratedRosterResult($assignments, $staffing, $hours, $preferences, $findings, $status, 'sha256:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }

    /** @return array<int|string> */
    private function rank(array $doctor, ?array $cell, array $totals): array
    {
        $remaining = (float) $doctor['required_hours'] - $totals['hours'];
        $target = max((float) $doctor['required_hours'], 1.0);

        return [($cell['preference'] ?? null) === 'preferred_work' ? 0 : 1, ($cell['explicit_availability'] ?? false) ? 0 : 1, $remaining > 0 ? 0 : 1, -$remaining, $totals['hours'] / $target, $totals['count'], ($cell['preference'] ?? null) === 'preferred_off' ? 1 : 0, $doctor['identifier']];
    }
}
