<?php

namespace App\Contracts\Rostering;

use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;

interface RosterAuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $previousValue
     * @param  array<string, mixed>|null  $newValue
     */
    public function record(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null, ?RosterPeriod $rosterPeriod = null): RosterAuditEntry;
}
