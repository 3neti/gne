<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterAssignment>
 */
class RosterAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => 'ASSIGNMENT-'.fake()->unique()->uuid(),
            'doctor_id' => Doctor::factory(),
            'roster_period_id' => RosterPeriod::factory(),
            'roster_day_id' => RosterDay::factory(),
            'status' => 'assigned',
            'source' => 'manual',
            'duty_code' => 'standard_day',
            'credited_hours' => 8,
        ];
    }
}
