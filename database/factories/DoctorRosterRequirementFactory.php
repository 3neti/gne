<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorRosterRequirement;
use App\Models\RosterPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorRosterRequirement>
 */
class DoctorRosterRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'roster_period_id' => RosterPeriod::factory(),
            'required_hours' => 80,
            'source' => 'manual',
            'notes' => null,
        ];
    }
}
