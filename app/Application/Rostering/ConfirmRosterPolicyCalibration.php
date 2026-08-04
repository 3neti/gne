<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\RosterPolicyDefinition;
use App\Domain\Rostering\RosterPolicyStatus;
use App\Models\RosterPolicyCalibration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ConfirmRosterPolicyCalibration
{
    public function __construct(private ResolveRosterPolicy $resolvePolicy, private RosterAuditRecorder $audit) {}

    /** @param array{policy_key: string, selected_value: string, effective_from?: string|null, source_reference: string, notes: string} $data */
    public function handle(User $actor, array $data): RosterPolicyCalibration
    {
        $current = $this->resolvePolicy->handle();
        $definition = $current->policies[$data['policy_key']] ?? throw new \InvalidArgumentException('Unknown policy key.');
        if (! in_array($data['selected_value'], RosterPolicyDefinition::allowedValues($data['policy_key']), true)) {
            throw new \InvalidArgumentException('The selected policy value is not supported by the current roster grammar.');
        }
        $revision = RosterPolicyCalibration::query()->where('policy_key', $data['policy_key'])->max('revision') + 1;
        $canonical = ['policy_key' => $data['policy_key'], 'revision' => $revision, 'selected_value' => $data['selected_value'], 'effective_from' => $data['effective_from'] ?? null, 'source_reference' => $data['source_reference']];

        return DB::transaction(function () use ($actor, $data, $definition, $canonical): RosterPolicyCalibration {
            $record = RosterPolicyCalibration::query()->create([...$canonical, 'identifier' => 'ROSTER-POLICY-'.Str::upper(Str::ulid()->toBase32()), 'department_key' => 'anaesthesia', 'status' => RosterPolicyStatus::Confirmed, 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'notes' => $data['notes'], 'fingerprint' => 'sha256:'.hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
            $this->audit->record($actor, 'roster_policy.confirmed', 'roster_policy_calibration', $record->identifier, $definition->toArray(), $record->only(['policy_key', 'revision', 'status', 'selected_value', 'effective_from', 'source_reference', 'fingerprint']), $data['notes']);

            return $record;
        });
    }
}
