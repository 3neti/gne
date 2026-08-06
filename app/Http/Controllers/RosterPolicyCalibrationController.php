<?php

namespace App\Http\Controllers;

use App\Application\Rostering\BuildRosterGenerationInput;
use App\Application\Rostering\ConfirmRosterPolicyCalibration;
use App\Application\Rostering\PreviewRosterPolicyImpact;
use App\Application\Rostering\ResolveRosterPolicy;
use App\Application\Rostering\ValidateResolvedRosterPolicyCoherence;
use App\Domain\Rostering\InvalidRosterPolicyConfirmation;
use App\Domain\Rostering\RosterPolicyEvaluationContext;
use App\Domain\Rostering\RosterPolicyEvaluationPurpose;
use App\Http\Requests\ConfirmRosterPolicyCalibrationRequest;
use App\Http\Requests\PreviewRosterPolicyImpactRequest;
use App\Models\RosterPeriod;
use App\Models\RosterPolicyCalibration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterPolicyCalibrationController extends Controller
{
    public function index(ResolveRosterPolicy $resolve, BuildRosterGenerationInput $buildInput, ValidateResolvedRosterPolicyCoherence $coherence): Response
    {
        Gate::authorize('viewAny', RosterPolicyCalibration::class);
        $period = RosterPeriod::query()->with(['days', 'doctorRequirements.doctor', 'scheduleRequests.dates'])->latest('start_date')->first();
        $context = $period === null ? null : new RosterPolicyEvaluationContext($period->start_date->toImmutable(), RosterPolicyEvaluationPurpose::GenerationPreview, $period->identifier);
        $policy = $resolve->handle($context);
        $readiness = $coherence->handle($policy, $period === null ? null : $buildInput->handle($period));

        return Inertia::render('rostering/PolicyCalibration', ['policy' => $policy->toArray(), 'readiness' => $readiness->toArray(), 'impact_preview' => session('policy_impact_preview'), 'abilities' => ['view_policy_calibration' => true, 'edit_policy_calibration' => Gate::allows('create', RosterPolicyCalibration::class), 'confirm_policy_calibration' => Gate::allows('confirmPolicyCalibration', RosterPolicyCalibration::class)]]);
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
