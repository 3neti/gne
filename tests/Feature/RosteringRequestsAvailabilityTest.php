<?php

use App\Application\Rostering\CreateRosterPeriod;
use App\Application\Rostering\RecordAcceptedDoctorScheduleRequest;
use App\Application\Rostering\RecordDoctorScheduleRequest;
use App\Application\Rostering\ResolveDoctorAvailability;
use App\Application\Rostering\TransitionDoctorScheduleRequest;
use App\Application\Rostering\TransitionRosterPeriod;
use App\Application\Rostering\ValidateDoctorRequests;
use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\OverlappingEffectiveDoctorRequest;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\DoctorRosterRequirement;
use App\Models\DoctorScheduleRequest;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;

function requestPeriod(User $actor, string $identifier = 'ROSTER-REQUEST-TEST'): RosterPeriod
{
    return app(CreateRosterPeriod::class)->handle($actor, ['identifier' => $identifier, 'title' => 'Request test period', 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'default_weekday_requirement' => 1, 'default_weekend_requirement' => 1]);
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
    $auditCount = RosterAuditEntry::query()->count();
    try {
        $record->handle($actor, $doctor, $period, DoctorRequestType::Available, ['2026-09-02', '2026-09-03']);
        $this->fail('Expected overlap rejection.');
    } catch (OverlappingEffectiveDoctorRequest $exception) {
        expect($exception->getMessage())->toContain('covers one or more selected dates')->not->toContain('equivalent')
            ->and($exception->overlappingDates)->toBe(['2026-09-02'])
            ->and($exception->requestIdentifiers)->toHaveCount(1);
    }
    expect(DoctorScheduleRequest::query()->count())->toBe(1)->and(RosterAuditEntry::query()->count())->toBe($auditCount);
});

it('creates and audits atomically and rolls back when audit fails', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = requestPeriod($actor);
    $audit = new class implements RosterAuditRecorder
    {
        public function record(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null): RosterAuditEntry
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
    expect($cell['effective_status'])->toBe('leave')->and($cell['explicit_availability'])->toBeFalse()->and($cell['blocking_status'])->toBe('leave')->and($cell['conflicted'])->toBeTrue()->and($cell['preference'])->toBe('conflicted')
        ->and($cell['conflict_codes'])->toBe(['REQUEST_LEAVE_AND_PREFERRED_WORK', 'REQUEST_PREFERRED_WORK_AND_PREFERRED_OFF']);
});

it('separates explicit availability unspecified eligibility and blocking states without double counting', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $period = requestPeriod($actor);
    $explicit = Doctor::factory()->create();
    $unspecified = Doctor::factory()->create();
    $unavailable = Doctor::factory()->create();
    $leave = Doctor::factory()->create();
    $inactive = Doctor::factory()->create(['active' => false]);
    foreach ([$explicit, $unspecified, $unavailable, $leave, $inactive] as $doctor) {
        DoctorRosterRequirement::factory()->create(['doctor_id' => $doctor, 'roster_period_id' => $period]);
    }
    $record = app(RecordAcceptedDoctorScheduleRequest::class);
    $record->handle($actor, $explicit, $period, DoctorRequestType::Available, ['2026-09-01']);
    $record->handle($actor, $unavailable, $period, DoctorRequestType::Unavailable, ['2026-09-01']);
    $record->handle($actor, $leave, $period, DoctorRequestType::Leave, ['2026-09-01']);
    $projection = app(ResolveDoctorAvailability::class)->handle($period);
    $day = collect($projection['calendar'])->firstWhere('date', '2026-09-01');
    $cells = collect($projection['availability'])->where('date', '2026-09-01')->keyBy('doctor_identifier');

    expect($cells[$unspecified->identifier]['effective_status'])->toBe('unspecified')
        ->and($cells[$explicit->identifier]['effective_status'])->toBe('available')
        ->and($day)->toMatchArray(['active_doctor_count' => 4, 'eligible_doctor_count' => 2, 'explicit_available_count' => 1, 'unspecified_doctor_count' => 1, 'unavailable_doctor_count' => 1, 'leave_doctor_count' => 1, 'staffing_input_status' => 'sufficient_eligible_pool'])
        ->and($day['active_doctor_count'])->toBe($day['eligible_doctor_count'] + $day['unavailable_doctor_count'] + $day['leave_doctor_count'])
        ->and($projection['availability'])->toHaveCount(4 * 28);
});

it('blocks readiness when the eligible pool is below the daily requirement but not when only explicit availability is low', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $period = requestPeriod($actor);
    $first = Doctor::factory()->create();
    $second = Doctor::factory()->create();
    DoctorRosterRequirement::factory()->create(['doctor_id' => $first, 'roster_period_id' => $period]);
    DoctorRosterRequirement::factory()->create(['doctor_id' => $second, 'roster_period_id' => $period]);
    app(TransitionRosterPeriod::class)->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
    $period->days()->update(['required_doctor_count' => 2]);
    $record = app(RecordAcceptedDoctorScheduleRequest::class);
    $record->handle($actor, $first, $period, DoctorRequestType::Available, ['2026-09-01']);
    $ready = app(TransitionRosterPeriod::class)->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);
    expect($ready->period->status)->toBe(RosterPeriodStatus::ReadyForGeneration);

    $blockedPeriod = requestPeriod($actor, 'ROSTER-REQUEST-BLOCKED');
    DoctorRosterRequirement::factory()->create(['doctor_id' => $first, 'roster_period_id' => $blockedPeriod]);
    DoctorRosterRequirement::factory()->create(['doctor_id' => $second, 'roster_period_id' => $blockedPeriod]);
    app(TransitionRosterPeriod::class)->handle($actor, $blockedPeriod, RosterPeriodStatus::CollectingRequests);
    $blockedPeriod->days()->update(['required_doctor_count' => 2]);
    $record->handle($actor, $first, $blockedPeriod, DoctorRequestType::Leave, ['2026-09-01']);
    expect(fn () => app(TransitionRosterPeriod::class)->handle($actor, $blockedPeriod->fresh(), RosterPeriodStatus::ReadyForGeneration))->toThrow(InvalidRosterTransition::class);
    $finding = collect(app(ValidateDoctorRequests::class)->handle($blockedPeriod->fresh()))->firstWhere('code', 'ELIGIBLE_POOL_BELOW_DAILY_REQUIREMENT');
    expect($finding)->not->toBeNull()->and($finding->date)->toBe('2026-09-01');
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
    $this->actingAs($actor)->get(route('rostering.periods.availability.show', $period))->assertSuccessful()->assertInertia(fn ($page) => $page->component('rostering/periods/Availability')->has('calendar', 28)->has('weeks', 4)->has('availabilitySummary')->where('calendar.0.unspecified_doctor_count', 0)->where('calendar.0.staffing_input_status', 'insufficient_eligible_pool'));
    $this->actingAs($ordinary)->get(route('rostering.requests.index'))->assertForbidden();
    $this->actingAs($ordinary)->get(route('rostering.periods.availability.show', $period))->assertForbidden();
});
