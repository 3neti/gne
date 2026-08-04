<?php

namespace App\Http\Controllers;

use App\Application\Rostering\ResolveDoctorAvailability;
use App\Models\RosterPeriod;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class RosterAvailabilityController extends Controller
{
    public function __invoke(RosterPeriod $rosterPeriod, ResolveDoctorAvailability $resolve): Response
    {
        Gate::authorize('view', $rosterPeriod);
        $projection = $resolve->handle($rosterPeriod);
        $doctorNames = collect($projection['doctors'])->pluck('name', 'identifier');

        return Inertia::render('rostering/periods/Availability', ['period' => ['identifier' => $rosterPeriod->identifier, 'title' => $rosterPeriod->title, 'start_date' => $rosterPeriod->start_date->toDateString(), 'end_date' => $rosterPeriod->end_date->toDateString(), 'status' => $rosterPeriod->status->value], 'calendar' => $projection['calendar'], 'weeks' => $projection['weeks'], 'doctors' => $projection['doctors'], 'doctorAvailabilityMatrix' => $projection['doctor_availability_matrix'], 'availabilitySummary' => $projection['availability_summary'], 'availability' => $projection['availability'], 'conflicts' => array_map(fn ($conflict): array => [...$conflict->toArray(), 'doctor_name' => $doctorNames->get($conflict->doctorIdentifier, $conflict->doctorIdentifier)], $projection['conflicts'])]);
    }
}
