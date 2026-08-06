<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\InvalidRosterPolicyConfirmation;
use App\Domain\Rostering\RosterPolicyConfirmability;
use App\Domain\Rostering\RosterPolicyConfirmationValidation;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyOption;
use App\Domain\Rostering\RosterPolicyParameterDefinition;
use Carbon\CarbonImmutable;

final readonly class ValidateRosterPolicyConfirmation
{
    /** @param array<string, mixed> $configuration */
    public function handle(RosterPolicyDefinition $definition, string $selectedValue, array $configuration, ?string $effectiveFrom = null, ?string $effectiveUntil = null): RosterPolicyConfirmationValidation
    {
        $option = collect($definition->options)->first(fn (RosterPolicyOption $candidate): bool => $candidate->value === $selectedValue);
        if (! $option instanceof RosterPolicyOption) {
            return new RosterPolicyConfirmationValidation($definition->key, $selectedValue, RosterPolicyConfirmability::Invalid, [], ['selected_value' => 'Unknown registered option.'], [], [], 'No impact can be evaluated for an unknown option.', []);
        }

        $knownKeys = [...array_keys($option->fixedConfiguration), ...array_map(fn (RosterPolicyParameterDefinition $parameter): string => $parameter->key, $option->parameters)];
        $invalid = [];
        foreach (array_keys($configuration) as $key) {
            if (! in_array($key, $knownKeys, true)) {
                $invalid[$key] = 'Unknown parameter.';
            }
        }
        foreach ($option->fixedConfiguration as $key => $fixedValue) {
            if (array_key_exists($key, $configuration) && $configuration[$key] !== $fixedValue) {
                $invalid[$key] = 'Fixed policy configuration cannot be changed.';
            }
        }
        $missing = [];
        $normalized = $option->fixedConfiguration;
        foreach ($option->parameters as $parameter) {
            $value = $configuration[$parameter->key] ?? null;
            if ($parameter->required && ($value === null || $value === '' || $value === [])) {
                $missing[] = $parameter->key;

                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $normalizedValue = $this->normalize($parameter, $value);
            if ($normalizedValue instanceof \InvalidArgumentException) {
                $invalid[$parameter->key] = $normalizedValue->getMessage();

                continue;
            }
            $normalized[$parameter->key] = $normalizedValue;
        }
        ksort($normalized);
        $windowFindings = [];
        if ($effectiveFrom !== null && $effectiveUntil !== null && CarbonImmutable::parse($effectiveUntil)->isBefore(CarbonImmutable::parse($effectiveFrom))) {
            $windowFindings[] = 'The effective end date precedes the start date.';
        }
        $confirmability = match (true) {
            ! $option->supported => RosterPolicyConfirmability::UnsupportedInCurrentRelease,
            ! $option->confirmationRequired || $option->value === 'unresolved' => RosterPolicyConfirmability::Unresolved,
            $invalid !== [] || $windowFindings !== [] => RosterPolicyConfirmability::Invalid,
            $missing !== [] => RosterPolicyConfirmability::ConfigurationRequired,
            default => RosterPolicyConfirmability::Confirmable,
        };

        return new RosterPolicyConfirmationValidation($definition->key, $selectedValue, $confirmability, $missing, $invalid, $option->unsupportedDependencies, $windowFindings, $option->impact, $normalized);
    }

    /** @param array<string, mixed> $configuration */
    public function confirmable(RosterPolicyDefinition $definition, string $selectedValue, array $configuration, ?string $effectiveFrom = null, ?string $effectiveUntil = null): RosterPolicyConfirmationValidation
    {
        $validation = $this->handle($definition, $selectedValue, $configuration, $effectiveFrom, $effectiveUntil);
        if (! $validation->isConfirmable()) {
            throw new InvalidRosterPolicyConfirmation($validation);
        }

        return $validation;
    }

    private function normalize(RosterPolicyParameterDefinition $parameter, mixed $value): mixed
    {
        return match ($parameter->type) {
            'integer' => $this->integer($parameter, $value),
            'boolean' => $this->boolean($value),
            'enum' => is_string($value) && in_array($value, $parameter->values, true) ? $value : new \InvalidArgumentException('Expected one of: '.implode(', ', $parameter->values).'.'),
            'enum_list' => $this->enumList($parameter, $value),
            default => new \InvalidArgumentException("Unsupported parameter type {$parameter->type}."),
        };
    }

    private function integer(RosterPolicyParameterDefinition $parameter, mixed $value): int|\InvalidArgumentException
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            return new \InvalidArgumentException('Expected an integer.');
        }
        $integer = (int) $value;
        if ($parameter->minimum !== null && $integer < $parameter->minimum) {
            return new \InvalidArgumentException("Minimum value is {$parameter->minimum}.");
        }

        return $integer;
    }

    private function boolean(mixed $value): bool|\InvalidArgumentException
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 1 || $value === '1' || $value === 'true' => true,
            $value === 0 || $value === '0' || $value === 'false' => false,
            default => new \InvalidArgumentException('Expected a boolean.'),
        };
    }

    private function enumList(RosterPolicyParameterDefinition $parameter, mixed $value): array|\InvalidArgumentException
    {
        if (! is_array($value) || $value === [] || array_filter($value, fn (mixed $item): bool => ! is_string($item) || ! in_array($item, $parameter->values, true)) !== []) {
            return new \InvalidArgumentException('Expected one or more values from: '.implode(', ', $parameter->values).'.');
        }
        $normalized = array_values(array_unique($value));
        sort($normalized);

        return $normalized;
    }
}
