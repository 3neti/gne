<?php

namespace Database\Factories;

use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterAuditEntry>
 */
class RosterAuditEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => 'doctor.created',
            'entity_type' => 'doctor',
            'entity_identifier' => 'DOCTOR-000001',
            'previous_value' => null,
            'new_value' => ['active' => true],
            'reason' => null,
        ];
    }

    public function forRosterPeriod(?RosterPeriod $rosterPeriod = null): static
    {
        return $this->state(fn (): array => ['roster_period_id' => $rosterPeriod?->id ?? RosterPeriod::factory()]);
    }
}
