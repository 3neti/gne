<?php

namespace App\Http\Controllers;

use App\Application\Rostering\RecordDoctorScheduleRequest;
use App\Application\Rostering\TransitionDoctorScheduleRequest;
use App\Application\Rostering\UpdateDoctorScheduleRequest;
use App\Application\Rostering\ValidateDoctorRequests;
use App\Domain\Rostering\DoctorRequestStatus;
use App\Domain\Rostering\DoctorRequestType;
use App\Domain\Rostering\RosterPeriodStatus;
use App\Http\Requests\StoreDoctorScheduleRequestRequest;
use App\Http\Requests\UpdateDoctorScheduleRequestRequest;
use App\Models\Doctor;
use App\Models\DoctorScheduleRequest;
use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class DoctorScheduleRequestController extends Controller
{
    public function index(Request $request, ValidateDoctorRequests $validate): Response
    {
        Gate::authorize('viewAny', DoctorScheduleRequest::class);
        $query = DoctorScheduleRequest::query()->with(['doctor', 'rosterPeriod', 'dates'])->latest('starts_on');
        if ($request->filled('period')) {
            $query->whereHas('rosterPeriod', fn ($builder) => $builder->where('identifier', $request->string('period')));
        }
        if ($request->filled('doctor')) {
            $query->whereHas('doctor', fn ($builder) => $builder->where('identifier', $request->string('doctor')));
        }
        if ($request->filled('type')) {
            $query->where('request_type', $request->string('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('date')) {
            $query->whereHas('dates', fn ($builder) => $builder->whereDate('date', $request->string('date')));
        }
        $periods = RosterPeriod::query()->latest('start_date')->get();
        $conflicted = $periods->flatMap(fn (RosterPeriod $period) => collect($validate->handle($period))->flatMap->requestIdentifiers)->unique()->all();
        $requests = $query->get()->map(fn (DoctorScheduleRequest $item): array => $this->data($item, in_array($item->identifier, $conflicted, true)));
        if ($request->filled('conflict')) {
            $hasConflict = $request->string('conflict')->toString() === 'yes';
            $requests = $requests->filter(fn (array $item): bool => $item['conflicted'] === $hasConflict)->values();
        }

        return Inertia::render('rostering/requests/Index', ['requests' => $requests, 'periods' => $periods->map(fn (RosterPeriod $period): array => ['identifier' => $period->identifier, 'title' => $period->title]), 'doctors' => Doctor::query()->orderBy('identifier')->get(['identifier', 'full_name']), 'filters' => $request->only(['period', 'doctor', 'type', 'status', 'date', 'conflict'])]);
    }

    public function create(): Response
    {
        Gate::authorize('create', DoctorScheduleRequest::class);

        return Inertia::render('rostering/requests/Form', ['requestRecord' => null, 'doctors' => Doctor::query()->where('active', true)->orderBy('identifier')->get(['identifier', 'full_name']), 'periods' => RosterPeriod::query()->whereIn('status', [RosterPeriodStatus::Draft, RosterPeriodStatus::CollectingRequests])->latest('start_date')->get(['identifier', 'title', 'start_date', 'end_date']), 'types' => array_map(fn (DoctorRequestType $type): string => $type->value, DoctorRequestType::cases()), 'statuses' => [DoctorRequestStatus::Submitted->value, DoctorRequestStatus::Accepted->value]]);
    }

    public function store(StoreDoctorScheduleRequestRequest $request, RecordDoctorScheduleRequest $record): RedirectResponse
    {
        $doctor = Doctor::query()->where('identifier', $request->validated('doctor'))->firstOrFail();
        $period = RosterPeriod::query()->where('identifier', $request->validated('roster_period'))->firstOrFail();
        $created = $record->handle($request->user(), $doctor, $period, DoctorRequestType::from($request->validated('request_type')), $this->dates($request->validated()), DoctorRequestStatus::from($request->validated('status')), $request->validated('reason'), $request->validated('notes'));

        return to_route('rostering.requests.show', $created)->with('success', 'Doctor schedule request recorded.');
    }

    public function show(DoctorScheduleRequest $doctorScheduleRequest, ValidateDoctorRequests $validate): Response
    {
        Gate::authorize('view', $doctorScheduleRequest);
        $doctorScheduleRequest->load(['doctor', 'rosterPeriod', 'dates']);
        $conflicts = collect($validate->handle($doctorScheduleRequest->rosterPeriod))->filter(fn ($conflict): bool => in_array($doctorScheduleRequest->identifier, $conflict->requestIdentifiers, true))->map->toArray()->values();
        $audit = RosterAuditEntry::query()->where('entity_type', 'doctor_schedule_request')->where('entity_identifier', $doctorScheduleRequest->identifier)->oldest()->get()->map(fn (RosterAuditEntry $entry): array => ['action' => $entry->action, 'reason' => $entry->reason, 'created_at' => $entry->created_at?->toIso8601String()]);

        return Inertia::render('rostering/requests/Show', ['requestRecord' => $this->data($doctorScheduleRequest, $conflicts->isNotEmpty()), 'conflicts' => $conflicts, 'audit' => $audit, 'allowedTransitions' => array_map(fn (DoctorRequestStatus $status): string => $status->value, $doctorScheduleRequest->status->allowedTransitions())]);
    }

    public function edit(DoctorScheduleRequest $doctorScheduleRequest): Response
    {
        Gate::authorize('update', $doctorScheduleRequest);

        return Inertia::render('rostering/requests/Form', ['requestRecord' => $this->data($doctorScheduleRequest->load(['doctor', 'rosterPeriod', 'dates']), false), 'doctors' => [], 'periods' => [], 'types' => [], 'statuses' => []]);
    }

    public function update(UpdateDoctorScheduleRequestRequest $request, DoctorScheduleRequest $doctorScheduleRequest, UpdateDoctorScheduleRequest $update): RedirectResponse
    {
        $update->handle($request->user(), $doctorScheduleRequest, $request->validated());

        return to_route('rostering.requests.show', $doctorScheduleRequest)->with('success', 'Request notes updated.');
    }

    public function destroy(DoctorScheduleRequest $doctorScheduleRequest): void
    {
        abort(405);
    }

    public function transition(Request $request, DoctorScheduleRequest $doctorScheduleRequest, TransitionDoctorScheduleRequest $transition): RedirectResponse
    {
        Gate::authorize('update', $doctorScheduleRequest);
        $validated = $request->validate(['status' => ['required', Rule::enum(DoctorRequestStatus::class)], 'reason' => ['nullable', 'string', 'max:2000']]);
        $transition->handle($request->user(), $doctorScheduleRequest, DoctorRequestStatus::from($validated['status']), $validated['reason'] ?? null);

        return back()->with('success', 'Request status updated.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function dates(array $data): array
    {
        if (($data['dates'] ?? []) !== []) {
            return array_values(array_map(fn (mixed $date): string => (string) $date, $data['dates']));
        }
        $dates = [];
        $date = CarbonImmutable::parse($data['starts_on']);
        $end = CarbonImmutable::parse($data['ends_on']);
        while ($date->lte($end)) {
            $dates[] = $date->toDateString();
            $date = $date->addDay();
        }

        return $dates;
    }

    /** @return array<string, mixed> */
    private function data(DoctorScheduleRequest $request, bool $conflicted): array
    {
        return ['identifier' => $request->identifier, 'doctor' => ['identifier' => $request->doctor->identifier, 'name' => $request->doctor->full_name], 'period' => ['identifier' => $request->rosterPeriod->identifier, 'title' => $request->rosterPeriod->title], 'request_type' => $request->request_type->value, 'status' => $request->status->value, 'starts_on' => $request->starts_on->toDateString(), 'ends_on' => $request->ends_on->toDateString(), 'dates' => $request->dates->pluck('date')->map->toDateString()->all(), 'reason' => $request->reason, 'notes' => $request->notes, 'conflicted' => $conflicted];
    }
}
