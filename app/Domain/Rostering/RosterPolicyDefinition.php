<?php

namespace App\Domain\Rostering;

final readonly class RosterPolicyDefinition
{
    /** @return list<string> */
    public static function allowedValues(string $key): array
    {
        return match ($key) {
            'unspecified_availability' => ['eligible_unless_blocked', 'explicit_availability_required', 'unresolved'],
            'required_hours_meaning' => ['roster_period_target', 'minimum_expected_hours', 'hard_maximum', 'planning_reference', 'unresolved'],
            'structural_hours_allocation' => ['equal_per_eligible_doctor', 'proportional_to_target_hours', 'unresolved'],
            'weekend_distribution' => ['informational', 'warning', 'mandatory', 'unresolved'],
            'consecutive_day_limit' => ['informational', 'warning', 'hard_limit', 'unresolved'],
            'target_hours_cap' => ['soft_target', 'hard_maximum', 'hard_minimum', 'target_with_authorized_excess', 'target_with_overtime', 'unresolved'],
            'employment_type_eligibility' => ['all_active_types_eligible', 'explicit_availability_by_type', 'unresolved'],
            'preference_strength' => ['soft_preference', 'preferred_off_prohibition', 'unresolved'],
            default => throw new \DomainException("Unknown roster policy key {$key}."),
        };
    }

    public function __construct(
        public string $identifier,
        public string $key,
        public int $revision,
        public RosterPolicyStatus $status,
        public string $selectedValue,
        public ?string $effectiveDate,
        public string $decisionAuthority,
        public string $sourceReference,
        public string $question,
        public string $generationImpact,
    ) {}

    public function isProvisional(): bool
    {
        return $this->status === RosterPolicyStatus::Provisional;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'key' => $this->key,
            'revision' => $this->revision,
            'status' => $this->status->value,
            'selected_value' => $this->selectedValue,
            'effective_date' => $this->effectiveDate,
            'decision_authority' => $this->decisionAuthority,
            'source_reference' => $this->sourceReference,
            'provisional' => $this->isProvisional(),
            'question' => $this->question,
            'generation_impact' => $this->generationImpact,
        ];
    }
}
