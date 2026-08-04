<?php

namespace App\Models;

use App\Domain\Rostering\RosterPolicyStatus;
use Database\Factories\RosterPolicyCalibrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['identifier', 'department_key', 'policy_key', 'revision', 'status', 'selected_value', 'configuration', 'effective_from', 'effective_until', 'confirmed_by', 'confirmed_at', 'source_reference', 'notes', 'fingerprint'])]
class RosterPolicyCalibration extends Model
{
    /** @use HasFactory<RosterPolicyCalibrationFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => RosterPolicyStatus::class, 'configuration' => 'array', 'effective_from' => 'date', 'effective_until' => 'date', 'confirmed_at' => 'datetime'];
    }
}
