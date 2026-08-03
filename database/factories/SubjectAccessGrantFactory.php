<?php

namespace Database\Factories;

use App\Domain\Authorization\SubjectPermission;
use App\Models\SubjectAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectAccessGrant>
 */
class SubjectAccessGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_identifier' => 'RESERVATION-'.fake()->unique()->numerify('######'),
            'permission' => SubjectPermission::View,
            'granted_at' => now(),
            'expires_at' => null,
            'metadata' => null,
        ];
    }
}
