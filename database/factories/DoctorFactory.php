<?php

namespace Database\Factories;

use App\Domain\Rostering\EmploymentType;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => 'DOCTOR-'.fake()->unique()->numerify('######'),
            'full_name' => fake()->name(),
            'employee_identifier' => fake()->optional()->numerify('00####'),
            'employment_type' => EmploymentType::FullTime,
            'active' => true,
            'contracted_hours' => null,
            'contracted_hours_period' => null,
            'standard_daily_hours' => 8,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function partTime(): static
    {
        return $this->state(fn (): array => ['employment_type' => EmploymentType::PartTime, 'contracted_hours' => 20, 'contracted_hours_period' => 'weekly', 'standard_daily_hours' => 8]);
    }
}
