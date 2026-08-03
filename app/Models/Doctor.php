<?php

namespace App\Models;

use App\Domain\Rostering\ContractedHoursPeriod;
use App\Domain\Rostering\EmploymentType;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['identifier', 'full_name', 'employee_identifier', 'employment_type', 'active', 'contracted_hours', 'contracted_hours_period', 'standard_daily_hours', 'notes'])]
class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory;

    protected $attributes = ['active' => true];

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return HasMany<DoctorRosterRequirement, $this> */
    public function rosterRequirements(): HasMany
    {
        return $this->hasMany(DoctorRosterRequirement::class);
    }

    /** @return HasMany<RosterAssignment, $this> */
    public function rosterAssignments(): HasMany
    {
        return $this->hasMany(RosterAssignment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['employment_type' => EmploymentType::class, 'contracted_hours_period' => ContractedHoursPeriod::class, 'active' => 'boolean', 'contracted_hours' => 'decimal:2', 'standard_daily_hours' => 'decimal:2'];
    }
}
