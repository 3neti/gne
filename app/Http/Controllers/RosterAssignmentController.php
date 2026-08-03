<?php

namespace App\Http\Controllers;

use App\Application\Rostering\BuildRosterCalendar;
use App\Application\Rostering\CreateRosterAssignment;
use App\Application\Rostering\MoveRosterAssignment;
use App\Application\Rostering\PreviewRosterMutation;
use App\Application\Rostering\RemoveRosterAssignment;
use App\Application\Rostering\ReplaceRosterAssignment;
use App\Domain\Rostering\DuplicatePrimaryRosterAssignment;
use App\Domain\Rostering\InvalidRosterAssignment;
use App\Http\Requests\MoveRosterAssignmentRequest;
use App\Http\Requests\PreviewRosterMutationRequest;
use App\Http\Requests\ReplaceRosterAssignmentRequest;
use App\Http\Requests\StoreRosterAssignmentRequest;
use App\Models\Doctor;
use App\Models\RosterAssignment;
use App\Models\RosterAuditEntry;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class RosterAssignmentController extends Controller
{
    public function index(RosterPeriod $rosterPeriod, BuildRosterCalendar $build): Response
    {
        Gate::authorize('view', $rosterPeriod);
        $projection = $build->handle($rosterPeriod);

        return Inertia::render('rostering/periods/Assignments', ['period' => ['identifier' => $rosterPeriod->identifier, 'title' => $rosterPeriod->title, 'status' => $rosterPeriod->status->value, 'start_date' => $rosterPeriod->start_date->toDateString(), 'end_date' => $rosterPeriod->end_date->toDateString()], ...$projection, 'doctors' => $rosterPeriod->doctorRequirements->filter(fn ($requirement): bool => $requirement->doctor->active)->sortBy(fn ($requirement): string => $requirement->doctor->identifier)->map(fn ($requirement): array => ['identifier' => $requirement->doctor->identifier, 'name' => $requirement->doctor->full_name])->values()->all(), 'revisions' => $rosterPeriod->revisions->sortByDesc('revision_number')->map(fn ($revision): array => ['identifier' => $revision->identifier, 'revision_number' => $revision->revision_number, 'reason' => $revision->reason, 'summary' => $revision->summary, 'validation_status' => $revision->validation_status, 'created_at' => $revision->created_at?->toIso8601String()])->values()->all(), 'audit' => RosterAuditEntry::query()->whereIn('action', ['roster_assignment.created', 'roster_assignment.removed', 'roster_assignment.moved', 'roster_assignment.replaced', 'roster_revision.created'])->latest()->limit(50)->get()->map(fn (RosterAuditEntry $entry): array => ['action' => $entry->action, 'entity_identifier' => $entry->entity_identifier, 'reason' => $entry->reason, 'created_at' => $entry->created_at?->toIso8601String()])->all(), 'feedback' => session('roster_feedback'), 'preview' => session('roster_preview')]);
    }

    public function store(StoreRosterAssignmentRequest $request, RosterPeriod $rosterPeriod, CreateRosterAssignment $create): RedirectResponse
    {
        $doctor = Doctor::query()->where('identifier', $request->validated('doctor_identifier'))->firstOrFail();
        $day = $this->day($rosterPeriod, $request->validated('date'));

        try {
            $result = $create->mutate($request->user(), $doctor, $rosterPeriod, $day, $request->safe()->only(['duty_code', 'start_time', 'end_time', 'credited_hours', 'notes', 'reason']));

            return back()->with('roster_feedback', $result->toArray());
        } catch (InvalidRosterAssignment|DuplicatePrimaryRosterAssignment $exception) {
            return back()->withErrors(['mutation' => $exception->getMessage()]);
        }
    }

    public function destroy(RosterPeriod $rosterPeriod, RosterAssignment $rosterAssignment, RemoveRosterAssignment $remove): RedirectResponse
    {
        Gate::authorize('update', $rosterPeriod);
        $this->ensureAssignmentBelongsToPeriod($rosterAssignment, $rosterPeriod);
        try {
            $result = $remove->handle(request()->user(), $rosterAssignment, request()->string('reason')->toString() ?: null);
        } catch (InvalidRosterAssignment $exception) {
            return back()->withErrors(['mutation' => $exception->getMessage()]);
        }

        return back()->with('roster_feedback', $result->toArray());
    }

    public function move(MoveRosterAssignmentRequest $request, RosterPeriod $rosterPeriod, RosterAssignment $rosterAssignment, MoveRosterAssignment $move): RedirectResponse
    {
        $this->ensureAssignmentBelongsToPeriod($rosterAssignment, $rosterPeriod);
        $target = $this->day($rosterPeriod, $request->validated('target_date'));
        try {
            $result = $move->handle($request->user(), $rosterAssignment, $target, $request->validated('reason'));

            return back()->with('roster_feedback', $result->toArray());
        } catch (InvalidRosterAssignment|DuplicatePrimaryRosterAssignment $exception) {
            return back()->withErrors(['mutation' => $exception->getMessage()]);
        }
    }

    public function replace(ReplaceRosterAssignmentRequest $request, RosterPeriod $rosterPeriod, RosterAssignment $rosterAssignment, ReplaceRosterAssignment $replace): RedirectResponse
    {
        $this->ensureAssignmentBelongsToPeriod($rosterAssignment, $rosterPeriod);
        $doctor = Doctor::query()->where('identifier', $request->validated('replacement_doctor_identifier'))->firstOrFail();
        try {
            $result = $replace->handle($request->user(), $rosterAssignment, $doctor, $request->validated('reason'));

            return back()->with('roster_feedback', $result->toArray());
        } catch (InvalidRosterAssignment|DuplicatePrimaryRosterAssignment $exception) {
            return back()->withErrors(['mutation' => $exception->getMessage()]);
        }
    }

    public function preview(PreviewRosterMutationRequest $request, RosterPeriod $rosterPeriod, PreviewRosterMutation $preview): RedirectResponse
    {
        $attributes = $request->validated();
        try {
            $result = match ($attributes['operation']) {
                'add' => $preview->add(Doctor::query()->where('identifier', $attributes['doctor_identifier'])->firstOrFail(), $rosterPeriod, $this->day($rosterPeriod, $attributes['date']), $attributes),
                'remove' => $preview->remove($this->assignment($rosterPeriod, $attributes['assignment_identifier'])),
                'move' => $preview->move($this->assignment($rosterPeriod, $attributes['assignment_identifier']), $this->day($rosterPeriod, $attributes['target_date'])),
                'replace' => $preview->replace($this->assignment($rosterPeriod, $attributes['assignment_identifier']), Doctor::query()->where('identifier', $attributes['replacement_doctor_identifier'])->firstOrFail()),
                default => throw new LogicException('Validated roster mutation operation is unsupported.'),
            };

            return back()->with('roster_preview', $result->toArray());
        } catch (InvalidRosterAssignment|DuplicatePrimaryRosterAssignment $exception) {
            return back()->withErrors(['mutation' => $exception->getMessage()]);
        }
    }

    private function day(RosterPeriod $period, string $date): RosterDay
    {
        return $period->days()->whereDate('date', $date)->firstOrFail();
    }

    private function assignment(RosterPeriod $period, string $identifier): RosterAssignment
    {
        return $period->assignments()->where('identifier', $identifier)->firstOrFail();
    }

    private function ensureAssignmentBelongsToPeriod(RosterAssignment $assignment, RosterPeriod $period): void
    {
        abort_unless($assignment->roster_period_id === $period->id, 404);
    }
}
