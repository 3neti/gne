<?php

namespace App\Http\Controllers;

use App\Application\Rostering\SetDailyStaffingRequirement;
use App\Http\Requests\UpdateRosterStaffingRequest;
use App\Models\RosterDay;
use App\Models\RosterPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterStaffingController extends Controller
{
    public function edit(RosterPeriod $rosterPeriod): Response
    {
        Gate::authorize('update', $rosterPeriod);

        return Inertia::render('rostering/periods/Staffing', ['period' => ['identifier' => $rosterPeriod->identifier, 'title' => $rosterPeriod->title], 'days' => $rosterPeriod->days()->get()->map(fn (RosterDay $day): array => ['id' => $day->id, 'date' => $day->date->toDateString(), 'weekday' => $day->date->format('l'), 'day_type' => $day->day_type->value, 'required_doctor_count' => $day->required_doctor_count])]);
    }

    public function update(UpdateRosterStaffingRequest $request, RosterPeriod $rosterPeriod, SetDailyStaffingRequirement $set): RedirectResponse
    {
        $validated = $request->validated();
        $set->handle($request->user(), $rosterPeriod, array_key_exists('weekday_default', $validated) && $validated['weekday_default'] !== null ? (int) $validated['weekday_default'] : null, array_key_exists('weekend_default', $validated) && $validated['weekend_default'] !== null ? (int) $validated['weekend_default'] : null, $validated['requirements'] ?? []);

        return back()->with('success', 'Staffing requirements updated.');
    }
}
