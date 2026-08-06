<?php

namespace App\Http\Controllers;

use App\Application\Rostering\ConfirmRosterPolicyCalibration;
use App\Application\Rostering\PreviewRosterPolicyImpact;
use App\Application\Rostering\ResolveRosterPolicy;
use App\Domain\Rostering\InvalidRosterPolicyConfirmation;
use App\Http\Requests\ConfirmRosterPolicyCalibrationRequest;
use App\Http\Requests\PreviewRosterPolicyImpactRequest;
use App\Models\RosterPolicyCalibration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterPolicyCalibrationController extends Controller
{
    public function index(ResolveRosterPolicy $resolve): Response
    {
        Gate::authorize('viewAny', RosterPolicyCalibration::class);
        $policy = $resolve->handle();

        return Inertia::render('rostering/PolicyCalibration', ['policy' => $policy->toArray(), 'impact_preview' => session('policy_impact_preview'), 'abilities' => ['view_policy_calibration' => true, 'edit_policy_calibration' => Gate::allows('create', RosterPolicyCalibration::class), 'confirm_policy_calibration' => Gate::allows('confirmPolicyCalibration', RosterPolicyCalibration::class)]]);
    }

    public function store(ConfirmRosterPolicyCalibrationRequest $request, ConfirmRosterPolicyCalibration $confirm): RedirectResponse
    {
        try {
            $record = $confirm->handle($request->user(), $request->validated());
        } catch (InvalidRosterPolicyConfirmation $exception) {
            return back()->withErrors(['configuration' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', "Confirmed {$record->policy_key} revision {$record->revision}.");
    }

    public function previewImpact(PreviewRosterPolicyImpactRequest $request, PreviewRosterPolicyImpact $preview): RedirectResponse
    {
        $data = $request->validated();

        return back()->with('policy_impact_preview', $preview->handle($data['policy_key'], $data['candidate_value'], $data['configuration'] ?? [])->toArray());
    }
}
