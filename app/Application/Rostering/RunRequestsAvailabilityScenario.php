<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\FoundationValidationSeverity;
use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\RequestsAvailabilityScenarioResult;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\RosterAuditEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class RunRequestsAvailabilityScenario
{
    public function __construct(private RegisterDoctor $registerDoctor, private CreateRosterPeriod $createPeriod, private SetDoctorRosterRequirement $setRequirement, private TransitionRosterPeriod $transitionPeriod, private RecordAcceptedDoctorScheduleRequest $recordAccepted, private TransitionDoctorScheduleRequest $transitionRequest, private ResolveDoctorAvailability $resolve, private ValidateDoctorRequests $validateRequests) {}

    public function handle(RosterLifecycleScenarioDefinition $scenario, bool $keepState = false): RequestsAvailabilityScenarioResult
    {
        DB::beginTransaction();
        try {
            $actor = User::query()->create(['name' => 'Requests Scenario Administrator', 'email' => 'requests-scenario@example.test', 'password' => 'not-used']);
            $names = ['Dr. Ana Reyes', 'Dr. Ben Cruz', 'Dr. Carla Santos', 'Dr. David Lim', 'Dr. Elena Flores', 'Dr. Felix Navarro', 'Dr. Gia Ramos', 'Dr. Hugo Tan', 'Dr. Iris Yu', 'Dr. Jules Co'];
            $doctors = collect($names)->map(fn (string $name, int $index): Doctor => $this->registerDoctor->handle($actor, ['full_name' => $name, 'employee_identifier' => sprintf('SCENARIO-%03d', $index + 1), 'employment_type' => 'full_time', 'standard_daily_hours' => 8, 'active' => true]));
            $period = $this->createPeriod->handle($actor, ['identifier' => 'ROSTER-REQUESTS-SCENARIO-2026-09', 'title' => 'September 2026 requests and availability proof', 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'default_weekday_requirement' => 7, 'default_weekend_requirement' => 5, 'notes' => 'Fictional lifecycle proof; no assignments generated.']);
            $doctors->each(fn (Doctor $doctor): mixed => $this->setRequirement->handle($actor, $period, $doctor, 160));
            $steps = [['sequence' => 1, 'id' => 'dataset', 'status' => 'passed', 'explanation' => 'Ten fictional doctors, 28 days, staffing demand, and required-hour targets created.']];
            $this->transitionPeriod->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
            $requests = collect();
            $requests->push($this->recordAccepted->handle($actor, $doctors[0], $period, DoctorRequestType::Unavailable, ['2026-09-05']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[0], $period, DoctorRequestType::PreferredWork, ['2026-09-12']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[1], $period, DoctorRequestType::Leave, ['2026-09-08', '2026-09-09', '2026-09-10']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[1], $period, DoctorRequestType::PreferredWork, ['2026-09-09']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[2], $period, DoctorRequestType::PreferredOff, ['2026-09-14']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[2], $period, DoctorRequestType::Available, ['2026-09-15', '2026-09-16']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[3], $period, DoctorRequestType::PreferredWork, ['2026-09-18']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[3], $period, DoctorRequestType::PreferredOff, ['2026-09-18']));
            $requests->push($this->recordAccepted->handle($actor, $doctors[4], $period, DoctorRequestType::Available, ['2026-09-20', '2026-09-22', '2026-09-24']));
            $hardConflict = $this->recordAccepted->handle($actor, $doctors[0], $period, DoctorRequestType::Available, ['2026-09-05']);
            $requests->push($hardConflict);
            $steps[] = ['sequence' => 2, 'id' => 'requests', 'status' => 'passed', 'explanation' => 'Availability, unavailability, leave, range, non-contiguous dates, and soft preferences recorded.'];
            $errors = collect($this->validateRequests->handle($period))->where('severity', FoundationValidationSeverity::Error);
            try {
                $this->transitionPeriod->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);
            } catch (InvalidRosterTransition) {
                $steps[] = ['sequence' => 3, 'id' => 'hard-conflict', 'status' => 'passed', 'explanation' => 'Available and unavailable conflict blocked readiness.', 'error_count' => $errors->count()];
            }
            if (! collect($steps)->contains('id', 'hard-conflict')) {
                throw new RuntimeException('Hard request conflict did not block readiness.');
            }
            $this->transitionRequest->handle($actor, $hardConflict, DoctorRequestStatus::Withdrawn, 'Resolve the controlled hard conflict.');
            $steps[] = ['sequence' => 4, 'id' => 'resolve-hard-conflict', 'status' => 'passed', 'explanation' => 'The conflicting availability request was withdrawn and remains historical.'];
            $transition = $this->transitionPeriod->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);
            $steps[] = ['sequence' => 5, 'id' => 'readiness', 'status' => 'passed', 'explanation' => 'No errors remain; soft conflicts stay visible as warnings.', 'warning_count' => count($transition->warnings())];
            $projection = $this->resolve->handle($period->fresh());
            $steps[] = ['sequence' => 6, 'id' => 'report', 'status' => 'passed', 'explanation' => 'Finalized availability projection contains no generated assignments.'];
            $conflicts = array_map(fn ($conflict): array => $conflict->toArray(), $projection['conflicts']);
            $requestIdentifiers = $requests->pluck('identifier')->all();
            $explanation = 'The roster period is ready for generation because no foundation or request-validation errors remain. The currently eligible doctor pool is sufficient for all dates under the interim assumption that unspecified active doctors are eligible. This does not guarantee that a valid or balanced roster can be generated.';
            $report = ['format' => 'gne-rostering-requests-availability/1.1', 'scenario' => ['identifier' => $scenario->identifier, 'title' => $scenario->title, 'passed' => true], 'lifecycle' => ['period_identifier' => $period->identifier, 'title' => $period->title, 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'status' => $period->fresh()->status->value, 'explanation' => $explanation], 'steps' => $steps, 'doctors' => $projection['doctors'], 'calendar' => ['dates' => $projection['calendar'], 'weeks' => $projection['weeks']], 'doctor_availability_matrix' => $projection['doctor_availability_matrix'], 'availability_summary' => $projection['availability_summary'], 'requests' => $projection['requests'], 'availability' => $projection['availability'], 'conflicts' => $conflicts, 'validation' => ['errors' => collect($conflicts)->where('severity', 'error')->count(), 'warnings' => collect($conflicts)->where('severity', 'warning')->count()], 'audit' => RosterAuditEntry::query()->where(fn ($query) => $query->where(fn ($query) => $query->where('entity_type', 'roster_period')->where('entity_identifier', $period->identifier))->orWhere(fn ($query) => $query->where('entity_type', 'doctor_schedule_request')->whereIn('entity_identifier', $requestIdentifiers)))->oldest()->get()->map(fn (RosterAuditEntry $entry): array => ['action' => $entry->action, 'entity_identifier' => $entry->entity_identifier])->all(), 'assignment_count' => $period->assignments()->count()];
            $result = new RequestsAvailabilityScenarioResult($report, $keepState);
            $keepState ? DB::commit() : DB::rollBack();

            return $result;
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}
