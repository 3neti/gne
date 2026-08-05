<?php

namespace App\Models;

use Database\Factories\RosterGenerationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['identifier', 'roster_period_id', 'generator_name', 'generator_version', 'policy_fingerprint', 'policy_evaluation_date', 'policy_snapshot', 'input_fingerprint', 'result_fingerprint', 'status', 'started_by', 'started_at', 'completed_at', 'summary', 'validation_status'])]
class RosterGenerationRun extends Model
{
    /** @use HasFactory<RosterGenerationRunFactory> */
    use HasFactory;

    public function rosterPeriod(): BelongsTo
    {
        return $this->belongsTo(RosterPeriod::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function explanations(): HasMany
    {
        return $this->hasMany(RosterAssignmentExplanation::class);
    }

    protected function casts(): array
    {
        return ['summary' => 'array', 'policy_evaluation_date' => 'date', 'policy_snapshot' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
