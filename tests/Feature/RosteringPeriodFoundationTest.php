<?php

use App\Application\Rostering\CreateRosterAssignment;
use App\Application\Rostering\CreateRosterPeriod;
use App\Application\Rostering\TransitionRosterPeriod;
use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterAuditEntry;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Database\QueryException;

function createFoundationPeriod(User $administrator, array $overrides = []): RosterPeriod
{
    return app(CreateRosterPeriod::class)->handle($administrator, [...['identifier' => 'ROSTER-2026-09', 'title' => 'Four week foundation', 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'default_weekday_requirement' => 6, 'default_weekend_requirement' => 3, 'notes' => null], ...$overrides]);
}

it('creates every inclusive roster day with deterministic classification and defaults', function () {
    $administrator = User::factory()->rosterAdministrator()->create();

    $period = createFoundationPeriod($administrator);

    expect($period->status)->toBe(RosterPeriodStatus::Draft)
        ->and($period->days)->toHaveCount(28)
        ->and($period->days->first()->date->toDateString())->toBe('2026-09-01')
        ->and($period->days->last()->date->toDateString())->toBe('2026-09-28')
        ->and($period->days->where('day_type', 'weekend')->every(fn (RosterDay $day): bool => $day->required_doctor_count === 3))->toBeTrue()
        ->and($period->days->where('day_type', 'normal')->every(fn (RosterDay $day): bool => $day->required_doctor_count === 6))->toBeTrue()
        ->and($period->days->pluck('date')->unique()->count())->toBe(28);
});

it('rejects reversed dates and duplicate public identifiers', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    createFoundationPeriod($administrator);

    $this->actingAs($administrator)->post(route('rostering.periods.store'), ['identifier' => 'ROSTER-2026-09', 'title' => 'Duplicate', 'start_date' => '2026-09-10', 'end_date' => '2026-09-01', 'default_weekday_requirement' => 1, 'default_weekend_requirement' => 1])->assertSessionHasErrors(['identifier', 'end_date']);
});

it('rolls back the period when day expansion fails', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $created = 0;
    RosterDay::created(function () use (&$created): void {
        $created++;
        if ($created === 2) {
            throw new RuntimeException('Synthetic day failure');
        }
    });

    expect(fn () => createFoundationPeriod($administrator))->toThrow(RuntimeException::class, 'Synthetic day failure');
    expect(RosterPeriod::query()->count())->toBe(0)->and(RosterDay::query()->count())->toBe(0)->and(RosterAuditEntry::query()->count())->toBe(0);
});

it('permits only declared early lifecycle transitions and audits them', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);

    app(TransitionRosterPeriod::class)->handle($administrator, $period, RosterPeriodStatus::CollectingRequests, 'Inputs may now be collected.');
    expect($period->fresh()->status)->toBe(RosterPeriodStatus::CollectingRequests)
        ->and(RosterAuditEntry::query()->where('action', 'roster_period.transitioned')->value('reason'))->toBe('Inputs may now be collected.');
    expect(fn () => app(TransitionRosterPeriod::class)->handle($administrator, $period->fresh(), RosterPeriodStatus::Published))->toThrow(InvalidRosterTransition::class);
});

it('requires complete foundation inputs before ready for generation', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);
    Doctor::factory()->create();
    app(TransitionRosterPeriod::class)->handle($administrator, $period, RosterPeriodStatus::CollectingRequests);

    expect(fn () => app(TransitionRosterPeriod::class)->handle($administrator, $period->fresh(), RosterPeriodStatus::ReadyForGeneration))->toThrow(InvalidRosterTransition::class, 'no findings');
});

it('enforces one primary assignment and preserves its optional seam', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create(['standard_daily_hours' => 7.5]);
    $period = createFoundationPeriod($administrator);
    $day = $period->days->first();

    $assignment = app(CreateRosterAssignment::class)->handle($administrator, $doctor, $period, $day, optional: ['start_time' => '08:00', 'end_time' => '16:30', 'credited_hours' => 7.5, 'notes' => 'Foundation proof']);

    expect($assignment->duty_code->value)->toBe('standard_day')->and($assignment->credited_hours)->toBe('7.50')->and($assignment->notes)->toBe('Foundation proof');
    expect(fn () => app(CreateRosterAssignment::class)->handle($administrator, $doctor, $period, $day))->toThrow(QueryException::class);
    expect(RosterAssignment::query()->count())->toBe(1);
});

it('rejects assignment for inactive doctor or day outside the period', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $period = createFoundationPeriod($administrator);
    $inactive = Doctor::factory()->inactive()->create();

    expect(fn () => app(CreateRosterAssignment::class)->handle($administrator, $inactive, $period, $period->days->first()))->toThrow(DomainException::class, 'Inactive doctors');

    $otherPeriod = RosterPeriod::factory()->create(['identifier' => 'ROSTER-OTHER']);
    $otherDay = RosterDay::factory()->create(['roster_period_id' => $otherPeriod->id, 'date' => $otherPeriod->start_date]);
    expect(fn () => app(CreateRosterAssignment::class)->handle($administrator, Doctor::factory()->create(), $period, $otherDay))->toThrow(DomainException::class, 'must belong');
});
