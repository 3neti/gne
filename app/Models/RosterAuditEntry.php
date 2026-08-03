<?php

namespace App\Models;

use Database\Factories\RosterAuditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'roster_period_id', 'action', 'entity_type', 'entity_identifier', 'previous_value', 'new_value', 'reason'])]
class RosterAuditEntry extends Model
{
    /** @use HasFactory<RosterAuditEntryFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<RosterPeriod, $this> */
    public function rosterPeriod(): BelongsTo
    {
        return $this->belongsTo(RosterPeriod::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['previous_value' => 'array', 'new_value' => 'array'];
    }
}
