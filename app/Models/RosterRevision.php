<?php

namespace App\Models;

use Database\Factories\RosterRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['identifier', 'roster_period_id', 'revision_number', 'created_by', 'reason', 'summary', 'validation_status', 'validation_snapshot'])]
class RosterRevision extends Model
{
    /** @use HasFactory<RosterRevisionFactory> */
    use HasFactory;

    /** @return BelongsTo<RosterPeriod, $this> */
    public function rosterPeriod(): BelongsTo
    {
        return $this->belongsTo(RosterPeriod::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<RosterRevisionChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(RosterRevisionChange::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['summary' => 'array', 'validation_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Roster revisions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Roster revisions are immutable.'));
    }
}
