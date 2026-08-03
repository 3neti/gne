<?php

use App\Application\Rostering\RegisterDoctor;
use App\Domain\Rostering\EmploymentType;
use App\Models\Doctor;
use App\Models\RosterAuditEntry;
use App\Models\User;

it('allows a roster administrator to create and update a doctor with stable identity', function () {
    $administrator = User::factory()->rosterAdministrator()->create();

    $response = $this->actingAs($administrator)->post(route('rostering.doctors.store'), [
        'full_name' => 'Ana Example', 'employee_identifier' => '001234', 'employment_type' => 'part_time',
        'contracted_hours' => 20, 'contracted_hours_period' => 'weekly', 'standard_daily_hours' => 8, 'notes' => 'Fictional clinician.',
    ]);

    $doctor = Doctor::query()->sole();
    $response->assertRedirect(route('rostering.doctors.edit', $doctor));
    expect($doctor->identifier)->toBe('DOCTOR-000001')
        ->and($doctor->employee_identifier)->toBe('001234')
        ->and($doctor->employment_type)->toBe(EmploymentType::PartTime)
        ->and(RosterAuditEntry::query()->where('action', 'doctor.created')->exists())->toBeTrue();

    $this->actingAs($administrator)->put(route('rostering.doctors.update', $doctor), [
        'full_name' => 'Ana Updated', 'employee_identifier' => '001234', 'employment_type' => 'part_time',
        'contracted_hours' => 24, 'contracted_hours_period' => 'weekly', 'standard_daily_hours' => 8, 'notes' => null,
    ])->assertRedirect();

    expect($doctor->fresh()->full_name)->toBe('Ana Updated')
        ->and(RosterAuditEntry::query()->where('action', 'doctor.updated')->count())->toBe(1);
});

it('validates the doctor contract pair and positive standard day', function (array $overrides, string $field) {
    $administrator = User::factory()->rosterAdministrator()->create();
    $payload = ['full_name' => 'Ben Example', 'employee_identifier' => null, 'employment_type' => 'full_time', 'contracted_hours' => null, 'contracted_hours_period' => null, 'standard_daily_hours' => 8, 'notes' => null];

    $this->actingAs($administrator)->post(route('rostering.doctors.store'), [...$payload, ...$overrides])->assertSessionHasErrors($field);
    expect(Doctor::query()->count())->toBe(0);
})->with([
    'hours without period' => [['contracted_hours' => 40], 'contracted_hours_period'],
    'period without hours' => [['contracted_hours_period' => 'weekly'], 'contracted_hours'],
    'zero standard day' => [['standard_daily_hours' => 0], 'standard_daily_hours'],
]);

it('deactivates a doctor while retaining the historical record', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();

    $this->actingAs($administrator)->delete(route('rostering.doctors.destroy', $doctor), ['reason' => 'No longer rostered'])->assertRedirect(route('rostering.doctors.index'));

    expect($doctor->fresh()->active)->toBeFalse()
        ->and(Doctor::query()->whereKey($doctor)->exists())->toBeTrue()
        ->and(RosterAuditEntry::query()->where('action', 'doctor.deactivated')->value('reason'))->toBe('No longer rostered');
});

it('prevents an ordinary authenticated user from managing doctors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('rostering.doctors.index'))->assertForbidden();
    $this->actingAs($user)->post(route('rostering.doctors.store'), ['full_name' => 'Blocked'])->assertForbidden();
});

it('register doctor action supports direct application use', function () {
    $administrator = User::factory()->rosterAdministrator()->create();

    $doctor = app(RegisterDoctor::class)->handle($administrator, ['full_name' => 'Cleo Example', 'employee_identifier' => null, 'employment_type' => 'visiting', 'active' => true, 'contracted_hours' => null, 'contracted_hours_period' => null, 'standard_daily_hours' => 7.5, 'notes' => null]);

    expect($doctor->identifier)->toStartWith('DOCTOR-')->and($doctor->standard_daily_hours)->toBe('7.50');
});
