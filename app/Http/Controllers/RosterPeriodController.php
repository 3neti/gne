<?php

namespace App\Http\Controllers;

use App\Application\Rostering\CreateRosterPeriod;
use App\Application\Rostering\TransitionRosterPeriod;
use App\Application\Rostering\UpdateRosterPeriod;
use App\Application\Rostering\ValidateDoctorRequests;
use App\Application\Rostering\ValidateRosterFoundation;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Http\Requests\StoreRosterPeriodRequest;
use App\Http\Requests\TransitionRosterPeriodRequest;
use App\Http\Requests\UpdateRosterPeriodRequest;
use App\Models\RosterPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterPeriodController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', RosterPeriod::class);

        return Inertia::render('rostering/periods/Index', ['periods' => RosterPeriod::query()->withCount('days')->latest('start_date')->get()->map(fn (RosterPeriod $period): array => $this->periodData($period))]);
    }

    public function create(): Response
    {
        Gate::authorize('create', RosterPeriod::class);

        return Inertia::render('rostering/periods/Create');
    }

    public function store(StoreRosterPeriodRequest $request, CreateRosterPeriod $create): RedirectResponse
    {
        $period = $create->handle($request->user(), $request->validated());

        return to_route('rostering.periods.show', $period)->with('success', 'Roster period created with its full calendar.');
    }

    public function show(RosterPeriod $rosterPeriod, ValidateRosterFoundation $validator, ValidateDoctorRequests $requestValidator): Response
    {
        Gate::authorize('view', $rosterPeriod);
        $rosterPeriod->loadCount(['days', 'doctorRequirements', 'assignments']);

        $findings = [...$validator->handle($rosterPeriod), ...$requestValidator->handle($rosterPeriod)];

        return Inertia::render('rostering/periods/Show', ['period' => $this->periodData($rosterPeriod), 'findings' => array_map(fn ($finding): array => $finding->toArray(), $findings), 'allowedTransitions' => array_map(fn (RosterPeriodStatus $status): string => $status->value, $rosterPeriod->status->allowedFoundationTransitions())]);
    }

    public function update(UpdateRosterPeriodRequest $request, RosterPeriod $rosterPeriod, UpdateRosterPeriod $update): RedirectResponse
    {
        $update->handle($request->user(), $rosterPeriod, $request->validated());

        return back()->with('success', 'Roster period updated.');
    }

    public function transition(TransitionRosterPeriodRequest $request, RosterPeriod $rosterPeriod, TransitionRosterPeriod $transition): RedirectResponse
    {
        $transition->handle($request->user(), $rosterPeriod, RosterPeriodStatus::from($request->validated('status')), $request->validated('reason'));

        return back()->with('success', 'Roster period transitioned.');
    }

    /** @return array<string, mixed> */
    private function periodData(RosterPeriod $period): array
    {
        return ['identifier' => $period->identifier, 'title' => $period->title, 'start_date' => $period->start_date->toDateString(), 'end_date' => $period->end_date->toDateString(), 'status' => $period->status->value, 'notes' => $period->notes, 'days_count' => $period->days_count ?? null, 'doctor_requirements_count' => $period->doctor_requirements_count ?? null, 'assignments_count' => $period->assignments_count ?? null];
    }
}
