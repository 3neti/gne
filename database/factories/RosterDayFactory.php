<?php

namespace Database\Factories;

use App\Models\RosterDay;
use App\Models\RosterPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterDay>
 */
class RosterDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roster_period_id' => RosterPeriod::factory(),
            'date' => now()->toDateString(),
            'day_type' => 'normal',
            'required_doctor_count' => 1,
            'notes' => null,
        ];
    }
}
