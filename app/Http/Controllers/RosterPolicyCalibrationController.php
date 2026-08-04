<?php

namespace App\Http\Controllers;

use App\Application\Rostering\ConfirmRosterPolicyCalibration;
use App\Application\Rostering\ResolveRosterPolicy;
use App\Http\Requests\ConfirmRosterPolicyCalibrationRequest;
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

        return Inertia::render('rostering/PolicyCalibration', ['policy' => $policy->toArray(), 'abilities' => ['view_policy_calibration' => true, 'edit_policy_calibration' => Gate::allows('create', RosterPolicyCalibration::class), 'confirm_policy_calibration' => Gate::allows('confirmPolicyCalibration', RosterPolicyCalibration::class)]]);
    }

    public function store(ConfirmRosterPolicyCalibrationRequest $request, ConfirmRosterPolicyCalibration $confirm): RedirectResponse
    {
        $record = $confirm->handle($request->user(), $request->validated());

        return back()->with('success', "Confirmed {$record->policy_key} revision {$record->revision}.");
    }
}
