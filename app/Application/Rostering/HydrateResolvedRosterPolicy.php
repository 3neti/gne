<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\ResolvedRosterPolicy;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyEvaluationContext;
use App\Domain\Rostering\RosterPolicyEvaluationPurpose;
use App\Domain\Rostering\RosterPolicyOption;
use App\Domain\Rostering\RosterPolicyStatus;
use Carbon\CarbonImmutable;

final readonly class HydrateResolvedRosterPolicy
{
    /** @param array<string, mixed> $snapshot */
    public function handle(array $snapshot): ResolvedRosterPolicy
    {
        $context = is_array($snapshot['evaluation_context'] ?? null)
            ? new RosterPolicyEvaluationContext(
                CarbonImmutable::parse((string) $snapshot['evaluation_context']['evaluation_date']),
                RosterPolicyEvaluationPurpose::from((string) $snapshot['evaluation_context']['purpose']),
                $snapshot['evaluation_context']['roster_period_identifier'] ?? null,
                $snapshot['evaluation_context']['generation_run_identifier'] ?? null,
            )
            : null;
        $policies = collect($snapshot['policies'] ?? [])->mapWithKeys(function (array $policy, string $key): array {
            $options = array_map(fn (array $option): RosterPolicyOption => new RosterPolicyOption(
                (string) $option['value'],
                (string) $option['label'],
                (string) $option['description'],
                (string) $option['impact'],
                (bool) ($option['confirmation_required'] ?? true),
            ), $policy['options'] ?? []);

            return [$key => new RosterPolicyDefinition(
                (string) $policy['identifier'],
                (string) $policy['key'],
                (int) $policy['revision'],
                RosterPolicyStatus::from((string) $policy['status']),
                (string) $policy['selected_value'],
                $policy['effective_date'] ?? null,
                (string) $policy['decision_authority'],
                (string) $policy['source_reference'],
                (string) $policy['question'],
                (string) $policy['generation_impact'],
                $policy['effective_until'] ?? null,
                (string) ($policy['effective_state'] ?? 'current'),
                (bool) ($policy['applicable'] ?? true),
                $options,
            )];
        })->all();

        return new ResolvedRosterPolicy(
            (string) $snapshot['profile_identifier'],
            (int) $snapshot['revision'],
            (string) $snapshot['generator_name'],
            (string) $snapshot['generator_version'],
            array_values($snapshot['provenance'] ?? []),
            (string) $snapshot['fingerprint'],
            $policies,
            $context,
            array_values($snapshot['future_policies'] ?? []),
            array_values($snapshot['expired_policies'] ?? []),
        );
    }
}
