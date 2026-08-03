<?php

namespace App\Models;

use App\Domain\Rostering\AssignmentSource;
use App\Domain\Rostering\AssignmentStatus;
use App\Domain\Rostering\DutyCode;
use Database\Factories\RosterAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['identifier', 'doctor_id', 'roster_period_id', 'roster_day_id', 'status', 'source', 'duty_code', 'start_time', 'end_time', 'credited_hours', 'notes', 'created_by'])]
class RosterAssignment extends Model
{
    /** @use HasFactory<RosterAssignmentFactory> */
    use HasFactory;

    protected $attributes = ['source' => 'manual'];

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

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

    /** @return BelongsTo<RosterDay, $this> */
    public function rosterDay(): BelongsTo
    {
        return $this->belongsTo(RosterDay::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => AssignmentStatus::class, 'source' => AssignmentSource::class, 'duty_code' => DutyCode::class, 'credited_hours' => 'decimal:2'];
    }
}
