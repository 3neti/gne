<?php

namespace App\Models;

use App\Domain\Rostering\RosterPeriodStatus;
use Database\Factories\RosterPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property RosterPeriodStatus $status
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
#[Fillable(['identifier', 'title', 'start_date', 'end_date', 'status', 'notes', 'created_by', 'published_at'])]
class RosterPeriod extends Model
{
    /** @use HasFactory<RosterPeriodFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'draft'];

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<RosterDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(RosterDay::class)->orderBy('date');
    }

    /** @return HasMany<DoctorRosterRequirement, $this> */
    public function doctorRequirements(): HasMany
    {
        return $this->hasMany(DoctorRosterRequirement::class);
    }

    /** @return HasMany<RosterAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RosterAssignment::class);
    }

    /** @return HasMany<RosterRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(RosterRevision::class)->orderBy('revision_number');
    }

    /** @return HasMany<DoctorScheduleRequest, $this> */
    public function scheduleRequests(): HasMany
    {
        return $this->hasMany(DoctorScheduleRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'status' => RosterPeriodStatus::class, 'published_at' => 'datetime'];
    }
}
