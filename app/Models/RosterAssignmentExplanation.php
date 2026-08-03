<?php

namespace App\Models;

use Database\Factories\RosterAssignmentExplanationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['roster_assignment_id', 'roster_generation_run_id', 'reason_codes', 'facts', 'ranking_position'])]
class RosterAssignmentExplanation extends Model
{
    /** @use HasFactory<RosterAssignmentExplanationFactory> */
    use HasFactory;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RosterAssignment::class, 'roster_assignment_id');
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(RosterGenerationRun::class, 'roster_generation_run_id');
    }

    protected function casts(): array
    {
        return ['reason_codes' => 'array', 'facts' => 'array'];
    }
}
