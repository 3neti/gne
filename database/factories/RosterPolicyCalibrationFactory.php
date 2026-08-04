<?php

namespace Database\Factories;

use App\Domain\Rostering\RosterPolicyStatus;
use App\Models\RosterPolicyCalibration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterPolicyCalibration>
 */
class RosterPolicyCalibrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => 'ROSTER-POLICY-'.fake()->unique()->regexify('[A-Z0-9]{26}'),
            'department_key' => 'anaesthesia',
            'policy_key' => 'structural_hours_allocation',
            'revision' => 1,
            'status' => RosterPolicyStatus::Provisional,
            'selected_value' => 'equal_per_eligible_doctor',
            'source_reference' => 'Department calibration meeting fixture',
            'notes' => 'Fictional test policy calibration.',
            'fingerprint' => 'sha256:'.hash('sha256', fake()->uuid()),
        ];
    }
}
