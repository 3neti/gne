<?php

namespace App\Http\Controllers;

use App\Application\Rostering\SetDoctorRosterRequirement;
use App\Domain\Rostering\RequirementSource;
use App\Http\Requests\UpdateDoctorRosterRequirementsRequest;
use App\Models\Doctor;
use App\Models\DoctorRosterRequirement;
use App\Models\RosterPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DoctorRosterRequirementController extends Controller
{
    public function edit(RosterPeriod $rosterPeriod): Response
    {
        Gate::authorize('update', $rosterPeriod);
        $requirements = DoctorRosterRequirement::query()->whereBelongsTo($rosterPeriod)->get()->keyBy('doctor_id');

        return Inertia::render('rostering/periods/Requirements', ['period' => ['identifier' => $rosterPeriod->identifier, 'title' => $rosterPeriod->title], 'doctors' => Doctor::query()->orderByDesc('active')->orderBy('full_name')->get()->map(function (Doctor $doctor) use ($requirements): array {
            $requirement = $requirements->get($doctor->id);

            return ['identifier' => $doctor->identifier, 'full_name' => $doctor->full_name, 'active' => $doctor->active, 'required_hours' => $requirement?->required_hours, 'source' => $requirement?->source->value, 'notes' => $requirement?->notes];
        })]);
    }

    public function update(UpdateDoctorRosterRequirementsRequest $request, RosterPeriod $rosterPeriod, SetDoctorRosterRequirement $set): RedirectResponse
    {
        $doctors = Doctor::query()->whereIn('identifier', array_keys($request->validated('requirements')))->get()->keyBy('identifier');
        foreach ($request->validated('requirements') as $identifier => $values) {
            if (($values['required_hours'] ?? null) === null || ! $doctors->has($identifier)) {
                continue;
            }
            $set->handle($request->user(), $rosterPeriod, $doctors->get($identifier), $values['required_hours'], RequirementSource::Manual, $values['notes'] ?? null);
        }

        return back()->with('success', 'Doctor required-hour targets updated.');
    }
}
