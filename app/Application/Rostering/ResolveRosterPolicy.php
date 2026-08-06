<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyEvaluationContext;
use App\Domain\Rostering\RosterPolicyOption;
use App\Domain\Rostering\RosterPolicyParameterDefinition;
use App\Domain\Rostering\RosterPolicyStatus;
use App\Domain\Rostering\StructuralHoursAllocationPolicy;
use App\Domain\Rostering\UnspecifiedAvailabilityPolicy;
use App\Models\RosterPolicyCalibration;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final readonly class ResolveRosterPolicy
{
    public function __construct(private Filesystem $files, private ValidateRosterPolicyConfirmation $validateConfirmation) {}

    public function handle(?RosterPolicyEvaluationContext $context = null): ResolvedRosterPolicy
    {
        $context ??= RosterPolicyEvaluationContext::current();
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

            $options = collect($data['options'] ?? [])->map(function (array $option): RosterPolicyOption {
                if (! array_key_exists('supported', $option) || ! array_key_exists('parameters', $option)) {
                    throw new \DomainException('Every roster policy option must declare supported and parameters metadata.');
                }
                $parameters = collect($option['parameters'] ?? [])->map(fn (array $parameter): RosterPolicyParameterDefinition => new RosterPolicyParameterDefinition((string) $parameter['key'], (string) $parameter['label'], (string) $parameter['type'], (bool) ($parameter['required'] ?? true), isset($parameter['minimum']) ? (int) $parameter['minimum'] : null, array_values($parameter['values'] ?? []), (string) ($parameter['description'] ?? '')))->all();

                return new RosterPolicyOption((string) $option['value'], (string) $option['label'], (string) $option['description'], (string) $option['impact'], (bool) ($option['confirmation_required'] ?? true), (bool) ($option['supported'] ?? true), $parameters, array_values($option['unsupported_dependencies'] ?? []), $option['fixed_configuration'] ?? [], (string) ($option['validation_impact'] ?? ''), (string) ($option['quality_impact'] ?? ''));
            })->all();
            if (array_map(fn (RosterPolicyOption $option): string => $option->value, $options) !== RosterPolicyDefinition::allowedValues((string) $data['key'])) {
                throw new \DomainException("Roster policy {$data['key']} options do not match the closed grammar.");
            }

            $selectedOption = collect($options)->first(fn (RosterPolicyOption $option): bool => $option->value === $data['selected_value']);
            $configuration = [...($selectedOption?->fixedConfiguration ?? []), ...($data['selected_configuration'] ?? [])];
            ksort($configuration);

            return [$data['key'] => new RosterPolicyDefinition((string) $data['identifier'], (string) $data['key'], (int) ($data['revision'] ?? 1), RosterPolicyStatus::from((string) $data['status']), (string) $data['selected_value'], isset($data['effective_date']) ? (string) $data['effective_date'] : null, (string) ($data['decision_authority'] ?? 'anaesthesia_department'), $path, (string) ($data['question'] ?? ''), (string) ($data['generation_impact'] ?? 'quality'), null, 'provisional', true, $options, $configuration)];
        });
        $future = [];
        $expired = [];
        if (RosterPolicyCalibration::query()->exists()) {
            $records = RosterPolicyCalibration::query()->with('confirmedBy')->orderBy('policy_key')->orderByDesc('revision')->get();
            foreach ($records as $record) {
                if ($record->status === RosterPolicyStatus::Confirmed && $record->effective_from?->isAfter($context->evaluationDate)) {
                    $future[] = $this->history($record, 'future_effective');
                } elseif (in_array($record->status, [RosterPolicyStatus::Confirmed, RosterPolicyStatus::Superseded], true) && $record->effective_until?->isBefore($context->evaluationDate)) {
                    $expired[] = $this->history($record, 'expired');
                }
            }
            $overrides = $records->filter(fn (RosterPolicyCalibration $record): bool => in_array($record->status, [RosterPolicyStatus::Confirmed, RosterPolicyStatus::Superseded], true) && ! $record->effective_from?->isAfter($context->evaluationDate) && ! $record->effective_until?->isBefore($context->evaluationDate))->unique('policy_key');
            foreach ($overrides as $override) {
                if (! $definitions->has($override->policy_key)) {
                    throw new \LogicException("Unknown calibrated roster policy {$override->policy_key}.");
                }
                $base = $definitions[$override->policy_key];
                if (! in_array($override->selected_value, RosterPolicyDefinition::allowedValues($override->policy_key), true)) {
                    throw new \DomainException("Roster policy {$override->policy_key} contains an unsupported calibrated value.");
                }
                $validation = $this->validateConfirmation->confirmable($base, $override->selected_value, $override->configuration ?? [], $override->effective_from?->toDateString(), $override->effective_until?->toDateString());
                $definitions[$override->policy_key] = new RosterPolicyDefinition($base->identifier, $base->key, $override->revision, $override->status, $override->selected_value, $override->effective_from?->toDateString(), $override->decision_authority ?? $override->confirmedBy?->name ?? 'department calibration record', $override->source_reference ?? $base->sourceReference, $base->question, $base->generationImpact, $override->effective_until?->toDateString(), 'current', true, $base->options, $validation->configuration);
            }
        }
        UnspecifiedAvailabilityPolicy::from($definitions['unspecified_availability']->selectedValue);
        StructuralHoursAllocationPolicy::from($definitions['structural_hours_allocation']->selectedValue);
        $content = json_encode($definitions->map->toArray()->sortKeys()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new ResolvedRosterPolicy('PROFILE-ANAESTHESIA-ROSTERING', (int) $definitions->max('revision'), 'balanced_greedy', '1.0', $paths, 'sha256:'.hash('sha256', $content), $definitions->all(), $context, $future, $expired);
    }

    /** @return array<string, mixed> */
    private function history(RosterPolicyCalibration $record, string $effectiveState): array
    {
        return ['identifier' => $record->identifier, 'policy_key' => $record->policy_key, 'revision' => $record->revision, 'status' => $record->status->value, 'selected_value' => $record->selected_value, 'configuration' => $record->configuration ?? [], 'effective_from' => $record->effective_from?->toDateString(), 'effective_until' => $record->effective_until?->toDateString(), 'effective_state' => $effectiveState, 'source_reference' => $record->source_reference];
    }
}
