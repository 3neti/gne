<?php

namespace App\Application\Rostering;

use App\Contracts\Rostering\RosterAuditRecorder;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\InvalidRosterTransition;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Domain\Rostering\RosterLifecycleScenarioResult;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Models\Doctor;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class RunRosterLifecycleScenario
{
    public function __construct(
        private CreateRosterPeriod $createPeriod,
        private RegisterDoctor $registerDoctor,
        private TransitionRosterPeriod $transition,
        private CreateRosterAssignment $createAssignment,
        private PrepareMissingRosterDayScenarioFixture $prepareMissingRosterDay,
        private ListRosterPeriodAuditEntries $listAudit,
    ) {}

    public function handle(RosterLifecycleScenarioDefinition $scenario, bool $keepState = false): RosterLifecycleScenarioResult
    {
        DB::beginTransaction();
        try {
            $actor = User::query()->create(['name' => 'Lifecycle Scenario Administrator', 'email' => 'rostering-scenario@example.test', 'password' => 'not-used', 'is_roster_administrator' => true]);
            $doctor = $this->registerDoctor->handle($actor, ['full_name' => 'Scenario Anaesthetist', 'employee_identifier' => 'SCENARIO-001', 'employment_type' => 'full_time', 'standard_daily_hours' => 8, 'active' => true]);
            $period = $this->createPeriod->handle($actor, ['identifier' => 'ROSTER-SCENARIO-WARNING', 'title' => 'Warning readiness proof', 'start_date' => '2026-09-01', 'end_date' => '2026-09-02', 'default_weekday_requirement' => 0, 'default_weekend_requirement' => 0]);
            $results = [];
            foreach ($scenario->steps as $sequence => $step) {
                $results[] = $this->runStep($sequence + 1, $step, $actor, $doctor, $period);
            }
            $result = new RosterLifecycleScenarioResult($scenario->identifier, collect($results)->every(fn (array $step): bool => $step['status'] === 'passed'), $results, $keepState);
            $keepState ? DB::commit() : DB::rollBack();

            return $result;
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @param  array{id: string, title: string, operation: string}  $step
     * @return array<string, mixed>
     */
    private function runStep(int $sequence, array $step, User $actor, Doctor $doctor, RosterPeriod $period): array
    {
        $before = $period->fresh()->status->value;
        $details = match ($step['operation']) {
            'warning_readiness' => $this->warningReadiness($actor, $period),
            'error_readiness' => $this->errorReadiness($actor),
            'assignment_audit' => $this->assignmentAudit($actor, $doctor, $period),
            'duplicate_rejection' => $this->duplicateRejection($actor, $doctor, $period),
            'audit_rollback' => $this->auditRollback($actor, $doctor, $period),
            default => throw new RuntimeException('Unsupported roster lifecycle operation.'),
        };

        return ['sequence' => $sequence, 'id' => $step['id'], 'title' => $step['title'], 'operation' => $step['operation'], 'status' => 'passed', 'status_before' => $before, 'status_after' => $period->fresh()->status->value, 'result' => $details];
    }

    /** @return array<string, mixed> */
    private function warningReadiness(User $actor, RosterPeriod $period): array
    {
        $this->transition->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
        $result = $this->transition->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);

        return ['transitioned' => true, 'warning_codes' => array_values(array_unique(array_map(fn ($finding): string => $finding->code, $result->warnings())))];
    }

    /** @return array<string, mixed> */
    private function errorReadiness(User $actor): array
    {
        $period = $this->createPeriod->handle($actor, ['identifier' => 'ROSTER-SCENARIO-ERROR', 'title' => 'Error readiness proof', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'default_weekday_requirement' => 1, 'default_weekend_requirement' => 1]);
        $this->transition->handle($actor, $period, RosterPeriodStatus::CollectingRequests);
        $this->prepareMissingRosterDay->handle($period);
        try {
            $this->transition->handle($actor, $period->fresh(), RosterPeriodStatus::ReadyForGeneration);
        } catch (InvalidRosterTransition) {
            return ['blocked' => true, 'status_unchanged' => $period->fresh()->status === RosterPeriodStatus::CollectingRequests];
        }

        throw new RuntimeException('The controlled foundation error did not block readiness.');
    }

    /** @return array<string, mixed> */
    private function assignmentAudit(User $actor, Doctor $doctor, RosterPeriod $period): array
    {
        $period->refresh();
        $assignment = $this->createAssignment->handle($actor, $doctor, $period, $period->days()->orderBy('date')->firstOrFail());

        return ['created' => true, 'audit_recorded' => $this->listAudit->handle($period, ['roster_assignment.created'])->contains('entity_identifier', $assignment->identifier)];
    }

    /** @return array<string, mixed> */
    private function duplicateRejection(User $actor, Doctor $doctor, RosterPeriod $period): array
    {
        $auditCount = $this->listAudit->handle($period)->count();
        try {
            $this->createAssignment->handle($actor, $doctor, $period, $period->days()->orderBy('date')->firstOrFail());
        } catch (DuplicatePrimaryRosterAssignment) {
            return ['rejected' => true, 'audit_count_unchanged' => $this->listAudit->handle($period)->count() === $auditCount];
        }

        throw new RuntimeException('The duplicate assignment was not rejected.');
    }

    /** @return array<string, mixed> */
    private function auditRollback(User $actor, Doctor $doctor, RosterPeriod $period): array
    {
        $assignmentCount = $period->assignments()->count();
        $failingAudit = new class implements RosterAuditRecorder
        {
            /**
             * @param  array<string, mixed>|null  $previousValue
             * @param  array<string, mixed>|null  $newValue
             */
            public function record(?User $actor, string $action, string $entityType, string $entityIdentifier, ?array $previousValue, ?array $newValue, ?string $reason = null, ?RosterPeriod $rosterPeriod = null): RosterAuditEntry
            {
                throw new RuntimeException('Controlled audit failure.');
            }
        };
        try {
            app()->instance(RosterAuditRecorder::class, $failingAudit);
            app(CreateRosterAssignment::class)->handle($actor, $doctor, $period->fresh(), $period->days()->orderBy('date')->skip(1)->firstOrFail());
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Controlled audit failure.') {
                return ['audit_failed' => true, 'assignment_count_unchanged' => $period->assignments()->count() === $assignmentCount];
            }
            throw $exception;
        }

        throw new RuntimeException('The controlled audit failure did not propagate.');
    }
}
