<?php

namespace Database\Factories;

use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterPeriod>
 */
class RosterPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => 'ROSTER-'.fake()->unique()->numerify('######'),
            'title' => fake()->monthName().' demonstration roster',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->startOfMonth()->addDays(27),
            'status' => 'draft',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
