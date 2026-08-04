<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyStatus;
use App\Domain\Rostering\StructuralHoursAllocationPolicy;
use App\Domain\Rostering\UnspecifiedAvailabilityPolicy;
use App\Models\RosterPolicyCalibration;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final readonly class ResolveRosterPolicy
{
    public function __construct(private Filesystem $files) {}

    public function handle(): ResolvedRosterPolicy
    {
        $calibrationFiles = ['unspecified-availability.yaml', 'required-hours-meaning.yaml', 'structural-hours-allocation.yaml', 'weekend-distribution.yaml', 'consecutive-day-limit.yaml', 'target-hours-cap.yaml', 'employment-type-eligibility.yaml', 'preference-strength.yaml'];
        $paths = collect($calibrationFiles)->map(fn (string $file): string => "business/profiles/anaesthesia-rostering/policies/{$file}")->all();
        $definitions = collect($paths)->mapWithKeys(function (string $path): array {
            $data = Yaml::parseFile(base_path($path));
            if (! is_array($data) || ! isset($data['key'], $data['identifier'], $data['selected_value'], $data['status'])) {
                throw new \LogicException("Roster policy source {$path} is invalid.");
            }
            if (! in_array((string) $data['selected_value'], RosterPolicyDefinition::allowedValues((string) $data['key']), true)) {
                throw new \DomainException("Roster policy {$data['key']} contains an unsupported value.");
            }

            return [$data['key'] => new RosterPolicyDefinition((string) $data['identifier'], (string) $data['key'], (int) ($data['revision'] ?? 1), RosterPolicyStatus::from((string) $data['status']), (string) $data['selected_value'], isset($data['effective_date']) ? (string) $data['effective_date'] : null, (string) ($data['decision_authority'] ?? 'anaesthesia_department'), $path, (string) ($data['question'] ?? ''), (string) ($data['generation_impact'] ?? 'quality'))];
        });
        if (RosterPolicyCalibration::query()->exists()) {
            $overrides = RosterPolicyCalibration::query()->orderBy('policy_key')->orderByDesc('revision')->get()->unique('policy_key');
            foreach ($overrides as $override) {
                if (! $definitions->has($override->policy_key)) {
                    throw new \LogicException("Unknown calibrated roster policy {$override->policy_key}.");
                }
                $base = $definitions[$override->policy_key];
                if (! in_array($override->selected_value, RosterPolicyDefinition::allowedValues($override->policy_key), true)) {
                    throw new \DomainException("Roster policy {$override->policy_key} contains an unsupported calibrated value.");
                }
                $definitions[$override->policy_key] = new RosterPolicyDefinition($base->identifier, $base->key, $override->revision, $override->status, $override->selected_value, $override->effective_from?->toDateString(), $override->confirmedBy?->name ?? 'department calibration record', $override->source_reference ?? $base->sourceReference, $base->question, $base->generationImpact);
            }
        }
        UnspecifiedAvailabilityPolicy::from($definitions['unspecified_availability']->selectedValue);
        StructuralHoursAllocationPolicy::from($definitions['structural_hours_allocation']->selectedValue);
        $content = json_encode($definitions->map->toArray()->sortKeys()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new ResolvedRosterPolicy('PROFILE-ANAESTHESIA-ROSTERING', (int) $definitions->max('revision'), 'balanced_greedy', '1.0', $paths, 'sha256:'.hash('sha256', $content), $definitions->all());
    }
}
