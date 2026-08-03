<?php

use App\Application\Rostering\CreateRosterPeriod;
use App\Application\Rostering\RecordAcceptedDoctorScheduleRequest;
use App\Application\Rostering\RecordDoctorScheduleRequest;
use App\Application\Rostering\RecordRosterAudit;
use App\Application\Rostering\ResolveDoctorAvailability;
use App\Application\Rostering\TransitionDoctorScheduleRequest;
use App\Application\Rostering\TransitionRosterPeriod;
use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\DoctorScheduleRequest;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;

function requestPeriod(User $actor): RosterPeriod
{
    return app(CreateRosterPeriod::class)->handle($actor, ['identifier' => 'ROSTER-REQUEST-TEST', 'title' => 'Request test period', 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'default_weekday_requirement' => 7, 'default_weekend_requirement' => 5]);
}

it('records one date ranges and non-contiguous dates as normalized request dates', function (array $dates) {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $request = app(RecordAcceptedDoctorScheduleRequest::class)->handle($actor, $doctor, $period, DoctorRequestType::Available, $dates);
    expect($request->identifier)->toMatch('/^REQUEST-\d{6}$/')->and($request->dates->pluck('date')->map->toDateString()->all())->toBe($dates);
})->with([[['2026-09-08']], [['2026-09-08', '2026-09-09', '2026-09-10']], [['2026-09-08', '2026-09-12', '2026-09-20']]]);

it('rejects empty outside-period inactive and duplicate effective requests before persistence', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $record = app(RecordAcceptedDoctorScheduleRequest::class);
    expect(fn () => $record->handle($actor, $doctor, $period, DoctorRequestType::Available, []))->toThrow(DomainException::class, 'at least one');
    expect(fn () => $record->handle($actor, $doctor, $period, DoctorRequestType::Available, ['2026-10-01']))->toThrow(DomainException::class, 'inside');
    $inactiveDoctor = Doctor::factory()->create(['active' => false]);
    expect(fn () => $record->handle($actor, $inactiveDoctor, $period, DoctorRequestType::Available, ['2026-09-03']))->toThrow(DomainException::class, 'active');
    $record->handle($actor, $doctor, $period, DoctorRequestType::Available, ['2026-09-02']);
    expect(fn () => $record->handle($actor, $doctor, $period, DoctorRequestType::Available, ['2026-09-02']))->toThrow(DomainException::class, 'equivalent');
    expect(DoctorScheduleRequest::query()->count())->toBe(1);
});

it('creates and audits atomically and rolls back when audit fails', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $audit = new class extends RecordRosterAudit
    {
        public function handle(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null): RosterAuditEntry
        {
            throw new RuntimeException('Audit unavailable');
        }
    };
    expect(fn () => (new RecordDoctorScheduleRequest($audit))->handle($actor, $doctor, $period, DoctorRequestType::Leave, ['2026-09-04']))->toThrow(RuntimeException::class);
    expect(DoctorScheduleRequest::query()->count())->toBe(0);
});

it('resolves precedence blocking preferences and deterministic conflicts', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $record = app(RecordAcceptedDoctorScheduleRequest::class);
    $record->handle($actor, $doctor, $period, DoctorRequestType::Leave, ['2026-09-08']);
    $record->handle($actor, $doctor, $period, DoctorRequestType::PreferredWork, ['2026-09-08']);
    $record->handle($actor, $doctor, $period, DoctorRequestType::PreferredOff, ['2026-09-08']);
    $cell = collect(app(ResolveDoctorAvailability::class)->handle($period)['availability'])->firstWhere(fn (array $item): bool => $item['doctor_identifier'] === $doctor->identifier && $item['date'] === '2026-09-08');
    expect($cell['effective_status'])->toBe('leave')->and($cell['blocking'])->toBeTrue()->and($cell['preference'])->toBe('conflicted')
        ->and($cell['conflict_codes'])->toBe(['REQUEST_LEAVE_AND_PREFERRED_WORK', 'REQUEST_PREFERRED_WORK_AND_PREFERRED_OFF']);
});

it('ignores rejected and withdrawn requests while preserving history', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $request = app(RecordAcceptedDoctorScheduleRequest::class)->handle($actor, $doctor, $period, DoctorRequestType::Unavailable, ['2026-09-09']);
    app(TransitionDoctorScheduleRequest::class)->handle($actor, $request, DoctorRequestStatus::Withdrawn);
    $cell = collect(app(ResolveDoctorAvailability::class)->handle($period)['availability'])->firstWhere(fn (array $item): bool => $item['doctor_identifier'] === $doctor->identifier && $item['date'] === '2026-09-09');
    expect($request->fresh()->status)->toBe(DoctorRequestStatus::Withdrawn)->and($cell['effective_status'])->toBe('unspecified')->and(DoctorScheduleRequest::query()->count())->toBe(1);
});

it('audits explicit acceptance and rejection transitions and excludes rejected evidence', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $submitted = app(RecordDoctorScheduleRequest::class)->handle($actor, $doctor, $period, DoctorRequestType::Unavailable, ['2026-09-11']);
    app(TransitionDoctorScheduleRequest::class)->handle($actor, $submitted, DoctorRequestStatus::Rejected, 'Coverage requirement');
    $accepted = app(RecordDoctorScheduleRequest::class)->handle($actor, $doctor, $period, DoctorRequestType::Available, ['2026-09-12']);
    app(TransitionDoctorScheduleRequest::class)->handle($actor, $accepted, DoctorRequestStatus::Accepted, 'Confirmed by administrator');
    $availability = collect(app(ResolveDoctorAvailability::class)->handle($period)['availability'])->where('doctor_identifier', $doctor->identifier)->keyBy('date');
    $actions = RosterAuditEntry::query()->where('entity_type', 'doctor_schedule_request')->pluck('action')->all();

    expect($availability['2026-09-11']['effective_status'])->toBe('unspecified')
        ->and($availability['2026-09-12']['effective_status'])->toBe('available')
        ->and($actions)->toContain('doctor_request.rejected', 'doctor_request.accepted');
});

it('accepts non-contiguous administrator dates and filters by date and conflict', function () {
    $this->withoutVite();
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);

    $this->actingAs($actor)->post(route('rostering.requests.store'), ['doctor' => $doctor->identifier, 'roster_period' => $period->identifier, 'request_type' => 'available', 'status' => 'accepted', 'dates_text' => "2026-09-04,\n2026-09-12", 'reason' => 'Confirmed dates'])->assertRedirect();
    app(RecordAcceptedDoctorScheduleRequest::class)->handle($actor, $doctor, $period, DoctorRequestType::Unavailable, ['2026-09-12']);

    $this->actingAs($actor)->get(route('rostering.requests.index', ['date' => '2026-09-04']))->assertSuccessful()->assertInertia(fn ($page) => $page->component('rostering/requests/Index')->has('requests', 1)->where('requests.0.dates', ['2026-09-04', '2026-09-12'])->where('requests.0.conflicted', true));
    $this->actingAs($actor)->get(route('rostering.requests.index', ['date' => '2026-09-12', 'conflict' => 'yes']))->assertSuccessful()->assertInertia(fn ($page) => $page->has('requests', 2)->where('requests.0.conflicted', true)->where('requests.1.conflicted', true));
});

it('blocks readiness on hard conflicts while warnings remain non-blocking', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $record = app(RecordAcceptedDoctorScheduleRequest::class);
    app(TransitionRosterPeriod::class)->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
    $record->handle($actor, $doctor, $period, DoctorRequestType::Available, ['2026-09-05']);
    $conflicting = $record->handle($actor, $doctor, $period, DoctorRequestType::Unavailable, ['2026-09-05']);
    expect(fn () => app(TransitionRosterPeriod::class)->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration))->toThrow(InvalidRosterTransition::class);
    app(TransitionDoctorScheduleRequest::class)->handle($actor, $conflicting, DoctorRequestStatus::Withdrawn);
    $record->handle($actor, $doctor, $period, DoctorRequestType::PreferredWork, ['2026-09-06']);
    $record->handle($actor, $doctor, $period, DoctorRequestType::PreferredOff, ['2026-09-06']);
    $result = app(TransitionRosterPeriod::class)->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);
    expect($result->period->status)->toBe(RosterPeriodStatus::ReadyForGeneration)->and($result->warnings())->not->toBeEmpty();
});

it('authorizes request and availability surfaces only for roster administrators', function () {
    $this->withoutVite();
    $actor = User::factory()->rosterAdministrator()->create();
    $ordinary = User::factory()->create();
    $period = requestPeriod($actor);
    $this->actingAs($actor)->get(route('rostering.requests.index'))->assertSuccessful()->assertInertia(fn ($page) => $page->component('rostering/requests/Index'));
    $this->actingAs($actor)->get(route('rostering.periods.availability.show', $period))->assertSuccessful()->assertInertia(fn ($page) => $page->component('rostering/periods/Availability')->has('calendar', 28));
    $this->actingAs($ordinary)->get(route('rostering.requests.index'))->assertForbidden();
    $this->actingAs($ordinary)->get(route('rostering.periods.availability.show', $period))->assertForbidden();
});
