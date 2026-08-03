<?php

namespace App\Models;

use Database\Factories\RosterRevisionChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['roster_revision_id', 'change_type', 'entity_identifier', 'before_value', 'after_value'])]
class RosterRevisionChange extends Model
{
    /** @use HasFactory<RosterRevisionChangeFactory> */
    use HasFactory;

    /** @return BelongsTo<RosterRevision, $this> */
    public function rosterRevision(): BelongsTo
    {
        return $this->belongsTo(RosterRevision::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before_value' => 'array', 'after_value' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Roster revision changes are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Roster revision changes are immutable.'));
    }
}
