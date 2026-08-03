<?php

use App\Application\Rostering\CreateRosterAssignment;
use App\Application\Rostering\MoveRosterAssignment;
use App\Application\Rostering\PreviewRosterMutation;
use App\Application\Rostering\RecordAcceptedDoctorScheduleRequest;
use App\Application\Rostering\RecordDoctorScheduleRequest;
use App\Application\Rostering\RemoveRosterAssignment;
use App\Application\Rostering\ReplaceRosterAssignment;
use App\Application\Rostering\TransitionDoctorScheduleRequest;
use App\Application\Rostering\ValidateRoster;
use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\InvalidRosterAssignment;
use App\Domain\Rostering\OverlappingEffectiveDoctorRequest;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\DoctorRosterRequirement;
use App\Models\RosterAssignment;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\RosterRevision;
use App\Models\User;

function manualRosterPeriod(User $actor, array $doctors): RosterPeriod
{
    $period = createFoundationPeriod($actor, ['identifier' => 'ROSTER-MANUAL-TEST', 'default_weekday_requirement' => 1, 'default_weekend_requirement' => 1]);
    foreach ($doctors as $doctor) {
        DoctorRosterRequirement::factory()->create(['doctor_id' => $doctor->id, 'roster_period_id' => $period->id, 'required_hours' => 8]);
    }
    $period->forceFill(['status' => RosterPeriodStatus::ReadyForGeneration])->save();

    return $period->fresh();
}

it('creates previews and mutates a roster with one immutable revision per action', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    [$ana, $ben, $cara] = Doctor::factory()->count(3)->create()->all();
    $period = manualRosterPeriod($actor, [$ana, $ben, $cara]);
    [$firstDay, $secondDay] = $period->days()->orderBy('date')->limit(2)->get()->all();

    $preview = app(PreviewRosterMutation::class)->add($ana, $period, $firstDay, ['credited_hours' => 8]);
    expect($preview->preview)->toBeTrue()
        ->and($preview->impact['daily_staffing'][0]['assigned_count'])->toBe(1)
        ->and(RosterAssignment::query()->count())->toBe(0)
        ->and(RosterRevision::query()->count())->toBe(0)
        ->and(RosterAuditEntry::query()->where('action', 'roster_assignment.created')->count())->toBe(0);

    $created = app(CreateRosterAssignment::class)->mutate($actor, $ana, $period, $firstDay, ['notes' => 'Manual proof']);
    expect($created->assignment?->credited_hours)->toBe('8.00')
        ->and($created->revision?->revision_number)->toBe(1)
        ->and($period->fresh()->status)->toBe(RosterPeriodStatus::Generated)
        ->and(RosterAuditEntry::query()->where('entity_identifier', $created->assignment?->identifier)->value('roster_period_id'))->toBe($period->id)
        ->and(RosterAuditEntry::query()->where('entity_identifier', $created->revision?->identifier)->value('roster_period_id'))->toBe($period->id);

    $moved = app(MoveRosterAssignment::class)->handle($actor, $created->assignment, $secondDay, 'Move proof');
    expect($moved->revision?->revision_number)->toBe(2)
        ->and($moved->assignment?->rosterDay->is($secondDay))->toBeTrue()
        ->and((float) $moved->assignment?->credited_hours)->toBe(8.0);

    $replaced = app(ReplaceRosterAssignment::class)->handle($actor, $moved->assignment, $ben, 'Replace proof');
    expect($replaced->revision?->revision_number)->toBe(3)
        ->and($replaced->assignment?->doctor->is($ben))->toBeTrue();

    $removed = app(RemoveRosterAssignment::class)->handle($actor, $replaced->assignment, 'Remove proof');
    expect($removed->revision?->revision_number)->toBe(4)
        ->and($removed->validation->status())->toBe('invalid')
        ->and(RosterAssignment::query()->count())->toBe(0)
        ->and(RosterRevision::query()->pluck('revision_number')->all())->toBe([1, 2, 3, 4])
        ->and(RosterRevision::query()->with('changes')->get()->every(fn (RosterRevision $revision): bool => $revision->changes->count() === 1))->toBeTrue();
    expect(fn () => RosterRevision::query()->firstOrFail()->update(['reason' => 'Rewrite history']))->toThrow(LogicException::class, 'immutable');
});

it('rejects leave unavailable duplicate inactive and invalid timing without durable success evidence', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    [$leaveDoctor, $unavailableDoctor, $activeDoctor] = Doctor::factory()->count(3)->create()->all();
    $inactiveDoctor = Doctor::factory()->inactive()->create();
    $period = manualRosterPeriod($actor, [$leaveDoctor, $unavailableDoctor, $activeDoctor, $inactiveDoctor]);
    $day = $period->days()->orderBy('date')->firstOrFail();
    app(RecordAcceptedDoctorScheduleRequest::class)->handle($actor, $leaveDoctor, $period, DoctorRequestType::Leave, [$day->date->toDateString()]);
    app(RecordAcceptedDoctorScheduleRequest::class)->handle($actor, $unavailableDoctor, $period, DoctorRequestType::Unavailable, [$day->date->toDateString()]);
    $baseline = [RosterRevision::query()->count(), RosterAuditEntry::query()->where('action', 'like', 'roster_assignment.%')->count()];

    expect(fn () => app(CreateRosterAssignment::class)->mutate($actor, $leaveDoctor, $period, $day))->toThrow(InvalidRosterAssignment::class)
        ->and(fn () => app(CreateRosterAssignment::class)->mutate($actor, $unavailableDoctor, $period, $day))->toThrow(InvalidRosterAssignment::class)
        ->and(fn () => app(CreateRosterAssignment::class)->mutate($actor, $inactiveDoctor, $period, $day))->toThrow(InvalidRosterAssignment::class)
        ->and(fn () => app(CreateRosterAssignment::class)->mutate($actor, $activeDoctor, $period, $day, ['credited_hours' => 0]))->toThrow(InvalidRosterAssignment::class)
        ->and(fn () => app(CreateRosterAssignment::class)->mutate($actor, $activeDoctor, $period, $day, ['start_time' => '16:00', 'end_time' => '08:00']))->toThrow(InvalidRosterAssignment::class);

    app(CreateRosterAssignment::class)->mutate($actor, $activeDoctor, $period, $day);
    expect(fn () => app(CreateRosterAssignment::class)->mutate($actor, $activeDoctor, $period->fresh(), $day))->toThrow(DuplicatePrimaryRosterAssignment::class)
        ->and(RosterAssignment::query()->count())->toBe(1)
        ->and(RosterRevision::query()->count())->toBe($baseline[0] + 1)
        ->and(RosterAuditEntry::query()->where('action', 'like', 'roster_assignment.%')->count())->toBe($baseline[1] + 1);
});

it('classifies roster staffing hours and preferences deterministically', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    [$ana, $ben] = Doctor::factory()->count(2)->create()->all();
    $period = manualRosterPeriod($actor, [$ana, $ben]);
    $period->doctorRequirements()->where('doctor_id', $ben->id)->update(['required_hours' => 16]);
    $day = $period->days()->orderBy('date')->firstOrFail();
    app(RecordAcceptedDoctorScheduleRequest::class)->handle($actor, $ana, $period, DoctorRequestType::PreferredOff, [$day->date->toDateString()]);
    app(CreateRosterAssignment::class)->mutate($actor, $ana, $period, $day);
    app(CreateRosterAssignment::class)->mutate($actor, $ben, $period->fresh(), $day);

    $validation = app(ValidateRoster::class)->handle($period->fresh());
    $codes = array_column($validation->toArray()['findings'], 'code');
    expect($validation->status())->toBe('invalid')
        ->and($codes)->toContain('ASSIGNMENT_PREFERRED_OFF', 'ROSTER_DAY_OVERSTAFFED', 'ROSTER_DAY_UNDERSTAFFED', 'DOCTOR_HOURS_BELOW_TARGET')
        ->and($codes)->toBe(collect($validation->toArray()['findings'])->pluck('code')->all());
});

it('rejects overlap when submitted requests transition to accepted', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = manualRosterPeriod($actor, [$doctor]);
    $record = app(RecordDoctorScheduleRequest::class);
    $first = $record->handle($actor, $doctor, $period, DoctorRequestType::Leave, ['2026-09-03', '2026-09-04']);
    $second = $record->handle($actor, $doctor, $period, DoctorRequestType::Leave, ['2026-09-04', '2026-09-05']);
    app(TransitionDoctorScheduleRequest::class)->handle($actor, $first, DoctorRequestStatus::Accepted);
    $auditCount = RosterAuditEntry::query()->count();

    expect(fn () => app(TransitionDoctorScheduleRequest::class)->handle($actor, $second, DoctorRequestStatus::Accepted))->toThrow(OverlappingEffectiveDoctorRequest::class)
        ->and($second->fresh()->status)->toBe(DoctorRequestStatus::Submitted)
        ->and(RosterAuditEntry::query()->count())->toBe($auditCount);
});

it('rolls assignment revision and audit back together when audit recording fails', function () {
    $actor = User::factory()->rosterAdministrator()->create();
    $doctor = Doctor::factory()->create();
    $period = manualRosterPeriod($actor, [$doctor]);
    app()->instance(RosterAuditRecorder::class, new class implements RosterAuditRecorder
    {
        public function record(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null, ?RosterPeriod $rosterPeriod = null): RosterAuditEntry
        {
            throw new RuntimeException('Controlled audit failure.');
        }
    });

    expect(fn () => app(CreateRosterAssignment::class)->mutate($actor, $doctor, $period, $period->days()->firstOrFail()))->toThrow(RuntimeException::class, 'Controlled audit failure')
        ->and(RosterAssignment::query()->count())->toBe(0)
        ->and(RosterRevision::query()->count())->toBe(0);
});

it('authorizes only roster administrators for the manual roster', function () {
    $administrator = User::factory()->rosterAdministrator()->create();
    $ordinary = User::factory()->create(['is_roster_administrator' => false]);
    $doctor = Doctor::factory()->create();
    $period = manualRosterPeriod($administrator, [$doctor]);

    $this->actingAs($administrator)->get(route('rostering.periods.roster.show', $period))->assertOk()->assertInertia(fn ($page) => $page->component('rostering/periods/Assignments')->has('calendar', 28));
    $this->actingAs($ordinary)->get(route('rostering.periods.roster.show', $period))->assertForbidden();
    $this->actingAs($ordinary)->post(route('rostering.periods.assignments.store', $period), ['doctor_identifier' => $doctor->identifier, 'date' => '2026-09-01', 'duty_code' => 'standard_day'])->assertForbidden();
});
