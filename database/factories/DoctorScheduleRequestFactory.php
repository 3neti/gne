<?php

namespace Database\Factories;

use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Models\Doctor;
use App\Models\DoctorScheduleRequest;
use App\Models\RosterPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorScheduleRequest>
 */
class DoctorScheduleRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => 'REQUEST-'.fake()->unique()->numerify('######'), 'doctor_id' => Doctor::factory(), 'roster_period_id' => RosterPeriod::factory(),
            'request_type' => DoctorRequestType::Available, 'status' => DoctorRequestStatus::Accepted, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'submitted_at' => now(),
        ];
    }
}
