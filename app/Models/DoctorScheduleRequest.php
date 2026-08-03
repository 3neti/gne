<?php

namespace App\Models;

use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use Database\Factories\DoctorScheduleRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property DoctorRequestType $request_type
 * @property DoctorRequestStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Doctor $doctor
 * @property RosterPeriod $rosterPeriod
 * @property Collection<int, DoctorScheduleRequestDate> $dates
 */
#[Fillable(['identifier', 'doctor_id', 'roster_period_id', 'request_type', 'status', 'starts_on', 'ends_on', 'reason', 'notes', 'submitted_at', 'submitted_by', 'reviewed_at', 'reviewed_by'])]
class DoctorScheduleRequest extends Model
{
    /** @use HasFactory<DoctorScheduleRequestFactory> */
    use HasFactory;

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

    /** @return HasMany<DoctorScheduleRequestDate, $this> */
    public function dates(): HasMany
    {
        return $this->hasMany(DoctorScheduleRequestDate::class)->orderBy('date');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['request_type' => DoctorRequestType::class, 'status' => DoctorRequestStatus::class, 'starts_on' => 'date', 'ends_on' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
