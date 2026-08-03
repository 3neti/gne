<?php

namespace App\Http\Controllers;

use App\Application\Rostering\BuildRosterGenerationInput;
use App\Application\Rostering\GenerateDraftRoster;
use App\Application\Rostering\PreviewDraftRosterGeneration;
use App\Application\Rostering\ResolveRosterPolicy;
use App\Domain\Rostering\InvalidRosterGeneration;
use App\Models\RosterPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterGenerationController extends Controller
{
    public function show(RosterPeriod $rosterPeriod, BuildRosterGenerationInput $buildInput, ResolveRosterPolicy $resolvePolicy): Response
    {
        Gate::authorize('generate', $rosterPeriod);
        $input = $buildInput->handle($rosterPeriod);
        $policy = $resolvePolicy->handle();

        return Inertia::render('rostering/periods/Generation', ['period' => ['identifier' => $rosterPeriod->identifier, 'title' => $rosterPeriod->title, 'status' => $rosterPeriod->status->value], 'readiness' => ['doctor_count' => count($input->doctors), 'date_count' => count($input->days), 'required_slots' => array_sum(array_column($input->days, 'required')), 'required_hours_complete' => collect($input->doctors)->every(fn (array $doctor): bool => (float) $doctor['required_hours'] >= 0), 'existing_assignments' => $input->existingAssignmentCount], 'generator' => $policy->toArray(), 'preview' => session('generation_preview'), 'committed' => session('generation_committed')]);
    }

    public function preview(RosterPeriod $rosterPeriod, PreviewDraftRosterGeneration $preview): RedirectResponse
    {
        Gate::authorize('generate', $rosterPeriod);
        try {
            return back()->with('generation_preview', $preview->handle($rosterPeriod)->toArray());
        } catch (InvalidRosterGeneration $exception) {
            return back()->withErrors(['generation' => $exception->getMessage()]);
        }
    }

    public function store(RosterPeriod $rosterPeriod, GenerateDraftRoster $generate): RedirectResponse
    {
        Gate::authorize('generate', $rosterPeriod);
        try {
            $result = $generate->handle(request()->user(), $rosterPeriod);

            return back()->with('generation_committed', ['generation_identifier' => $result['generation_run']->identifier, 'revision_identifier' => $result['revision']->identifier, 'status' => $result['validation']->status()]);
        } catch (InvalidRosterGeneration $exception) {
            return back()->withErrors(['generation' => $exception->getMessage()]);
        }
    }
}
