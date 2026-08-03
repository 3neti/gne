<?php

namespace App\Models;

use App\Domain\Rostering\RequirementSource;
use Database\Factories\DoctorRosterRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property RequirementSource $source */
#[Fillable(['doctor_id', 'roster_period_id', 'required_hours', 'source', 'notes'])]
class DoctorRosterRequirement extends Model
{
    /** @use HasFactory<DoctorRosterRequirementFactory> */
    use HasFactory;

    protected $attributes = ['source' => 'manual'];

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return BelongsTo<RosterPeriod, $this> */
    public function rosterPeriod(): BelongsTo
    {
        return $this->belongsTo(RosterPeriod::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['required_hours' => 'decimal:2', 'source' => RequirementSource::class];
    }
}
