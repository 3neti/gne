<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyStatus;
use App\Models\RosterPolicyCalibration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ConfirmRosterPolicyCalibration
{
    public function __construct(private ResolveRosterPolicy $resolvePolicy, private RosterAuditRecorder $audit) {}

    /** @param array{policy_key: string, selected_value: string, effective_from: string, effective_until?: string|null, decision_authority: string, source_reference: string, notes: string} $data */
    public function handle(User $actor, array $data): RosterPolicyCalibration
    {
        $current = $this->resolvePolicy->handle();
        $definition = $current->policies[$data['policy_key']] ?? throw new \InvalidArgumentException('Unknown policy key.');
        if (! in_array($data['selected_value'], RosterPolicyDefinition::allowedValues($data['policy_key']), true)) {
            throw new \InvalidArgumentException('The selected policy value is not supported by the current roster grammar.');
        }

        return DB::transaction(function () use ($actor, $data, $definition): RosterPolicyCalibration {
            $start = CarbonImmutable::parse($data['effective_from']);
            $end = isset($data['effective_until']) ? CarbonImmutable::parse($data['effective_until']) : null;
            $records = RosterPolicyCalibration::query()->where('policy_key', $data['policy_key'])->orderBy('revision')->lockForUpdate()->get();
            $existing = $records->whereIn('status', [RosterPolicyStatus::Confirmed, RosterPolicyStatus::Superseded]);
            $revision = ((int) $records->max('revision')) + 1;
            $canonical = ['policy_key' => $data['policy_key'], 'revision' => $revision, 'selected_value' => $data['selected_value'], 'effective_from' => $data['effective_from'], 'effective_until' => $data['effective_until'] ?? null, 'decision_authority' => $data['decision_authority'], 'source_reference' => $data['source_reference']];
            foreach ($existing as $prior) {
                if ($prior->effective_from !== null && ! $prior->effective_from->isBefore($start) && ($end === null || ! $prior->effective_from->isAfter($end))) {
                    throw new \DomainException('The confirmed policy window overlaps an existing current or scheduled decision.');
                }
                if ($prior->effective_from?->isBefore($start) && ($prior->effective_until === null || ! $prior->effective_until->isBefore($start))) {
                    $prior->update(['effective_until' => $start->subDay()->toDateString(), 'status' => RosterPolicyStatus::Superseded]);
                    $this->audit->record($actor, 'roster_policy.superseded', 'roster_policy_calibration', $prior->identifier, null, ['effective_until' => $prior->effective_until?->toDateString(), 'superseded_by_revision' => $canonical['revision']], $data['notes']);
                }
            }
            $record = RosterPolicyCalibration::query()->create([...$canonical, 'identifier' => 'ROSTER-POLICY-'.Str::upper(Str::ulid()->toBase32()), 'department_key' => 'anaesthesia', 'status' => RosterPolicyStatus::Confirmed, 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'notes' => $data['notes'], 'fingerprint' => 'sha256:'.hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
            $this->audit->record($actor, 'roster_policy.confirmed', 'roster_policy_calibration', $record->identifier, $definition->toArray(), $record->only(['policy_key', 'revision', 'status', 'selected_value', 'effective_from', 'source_reference', 'fingerprint']), $data['notes']);
            if ($record->effective_from?->isAfter(CarbonImmutable::today())) {
                $this->audit->record($actor, 'roster_policy.scheduled', 'roster_policy_calibration', $record->identifier, null, $record->only(['policy_key', 'revision', 'effective_from', 'effective_until']), $data['notes']);
            }

            return $record;
        });
    }
}
