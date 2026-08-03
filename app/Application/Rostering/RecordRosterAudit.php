<?php

namespace App\Application\Rostering;

use App\Models\RosterAuditEntry;
use App\Models\User;

final class RecordRosterAudit
{
    /** @param array<string, mixed>|null $previousValue @param array<string, mixed>|null $newValue */
    public function handle(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null): RosterAuditEntry
    {
        return RosterAuditEntry::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_identifier' => $entityIdentifier,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'reason' => $reason,
        ]);
    }
}
