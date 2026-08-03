<?php

use App\Application\Rostering\SetDailyStaffingRequirement;
use App\Application\Rostering\SetDoctorRosterRequirement;
use App\Application\Rostering\ValidateRosterFoundation;
use App\Domain\Rostering\FoundationValidationSeverity;
use App\Domain\Rostering\RequirementSource;
use App\Models\Doctor;
use App\Models\DoctorRosterRequirement;
use App\Models\RosterAuditEntry;
use App\Models\User;

it('bulk applies staffing defaults while a specific date remains explicitly overridable', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);
    $specificDay = $period->days->first();

    app(SetDailyStaffingRequirement::class)->handle($administrator, $period, 7, 4, [$specificDay->id => 9]);

    expect($specificDay->fresh()->required_doctor_count)->toBe(9)
        ->and($period->days()->where('day_type', 'normal')->whereKeyNot($specificDay->id)->get()->every(fn ($day): bool => $day->required_doctor_count === 7))->toBeTrue()
        ->and($period->days()->where('day_type', 'weekend')->get()->every(fn ($day): bool => $day->required_doctor_count === 4))->toBeTrue()
        ->and(RosterAuditEntry::query()->where('action', 'roster_day.staffing_changed')->count())->toBe(28)
        ->and(RosterAuditEntry::query()->where('action', 'roster_day.staffing_changed')->where('roster_period_id', $period->id)->count())->toBe(28);
});

it('creates and updates one explicit doctor-period target with source preserved', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);
    $doctor = Doctor::factory()->create();

    $created = app(SetDoctorRosterRequirement::class)->handle($administrator, $period, $doctor, 120, RequirementSource::Manual, 'Foundation target');
    $updated = app(SetDoctorRosterRequirement::class)->handle($administrator, $period, $doctor, 128, RequirementSource::Manual, 'Adjusted target');

    expect($created->source)->toBe(RequirementSource::Manual)
        ->and($updated->required_hours)->toBe('128.00')
        ->and(DoctorRosterRequirement::query()->count())->toBe(1)
        ->and(RosterAuditEntry::query()->where('action', 'doctor_requirement.created')->count())->toBe(1)
        ->and(RosterAuditEntry::query()->where('action', 'doctor_requirement.updated')->count())->toBe(1)
        ->and(RosterAuditEntry::query()->whereIn('action', ['doctor_requirement.created', 'doctor_requirement.updated'])->where('roster_period_id', $period->id)->count())->toBe(2);
});

it('rejects negative staffing and required hours at the authorized HTTP boundary', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);
    $doctor = Doctor::factory()->create();

    $this->actingAs($administrator)->put(route('rostering.periods.staffing.update', $period), ['requirements' => [$period->days->first()->id => -1]])->assertSessionHasErrors('requirements.'.$period->days->first()->id);
    $this->actingAs($administrator)->put(route('rostering.periods.requirements.update', $period), ['requirements' => [$doctor->identifier => ['required_hours' => -1]]])->assertSessionHasErrors('requirements.'.$doctor->identifier.'.required_hours');
});

it('retains an inactive doctor historical target and reports only active missing targets', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);
    $inactive = Doctor::factory()->inactive()->create();
    $active = Doctor::factory()->create();
    app(SetDoctorRosterRequirement::class)->handle($administrator, $period, $inactive, 80);

    $findings = app(ValidateRosterFoundation::class)->handle($period->fresh());

    expect(DoctorRosterRequirement::query()->whereBelongsTo($inactive)->exists())->toBeTrue()
        ->and(collect($findings)->where('code', 'DOCTOR_REQUIREMENT_MISSING')->pluck('entityIdentifier')->all())->toBe([$active->identifier]);
});

it('reports missing roster days as deterministic errors and zero staffing as warnings', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);
    $days = $period->days()->get();
    $days->first()->delete();
    $days->get(1)->update(['required_doctor_count' => 0]);

    $findings = app(ValidateRosterFoundation::class)->handle($period->fresh());

    expect($findings[0]->severity)->toBe(FoundationValidationSeverity::Error)
        ->and($findings[0]->code)->toBe('ROSTER_DAY_MISSING')
        ->and(collect($findings)->contains(fn ($finding): bool => $finding->code === 'STAFFING_REQUIREMENT_ZERO' && $finding->severity === FoundationValidationSeverity::Warning))->toBeTrue();
});

it('renders the foundation application pages only for roster administrators', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);

    $this->actingAs($administrator)->get(route('rostering.dashboard'))->assertSuccessful()->assertInertia(fn ($page) => $page->component('rostering/Dashboard'));
    $this->actingAs($administrator)->get(route('rostering.periods.show', $period))->assertSuccessful()->assertInertia(fn ($page) => $page->component('rostering/periods/Show')->where('period.identifier', $period->identifier));
    $this->actingAs($administrator)->get(route('rostering.periods.staffing.edit', $period))->assertSuccessful();
    $this->actingAs($administrator)->get(route('rostering.periods.requirements.edit', $period))->assertSuccessful();
    $this->actingAs($administrator)->get(route('rostering.periods.assignments.index', $period))->assertSuccessful();
});
