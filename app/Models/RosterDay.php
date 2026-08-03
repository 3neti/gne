<?php

namespace App\Models;

use App\Domain\Rostering\RosterDayType;
use Database\Factories\RosterDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['roster_period_id', 'date', 'day_type', 'required_doctor_count', 'notes'])]
class RosterDay extends Model
{
    /** @use HasFactory<RosterDayFactory> */
    use HasFactory;

    /** @return BelongsTo<RosterPeriod, $this> */
    public function rosterPeriod(): BelongsTo
    {
        return $this->belongsTo(RosterPeriod::class);
    }

    /** @return HasMany<RosterAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RosterAssignment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'day_type' => RosterDayType::class, 'required_doctor_count' => 'integer'];
    }
}
