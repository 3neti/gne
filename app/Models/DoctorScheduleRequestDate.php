<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $date */
#[Fillable(['doctor_schedule_request_id', 'date'])]
class DoctorScheduleRequestDate extends Model
{
    /** @return BelongsTo<DoctorScheduleRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(DoctorScheduleRequest::class, 'doctor_schedule_request_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
