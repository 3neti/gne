<?php

namespace App\Http\Controllers;

use App\Application\Rostering\ValidateRosterFoundation;
use App\Models\Doctor;
use App\Models\RosterPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosteringDashboardController extends Controller
{
    public function __invoke(Request $request, ValidateRosterFoundation $validator): Response
    {
        Gate::authorize('viewAny', RosterPeriod::class);
        $period = RosterPeriod::query()->withCount(['days', 'doctorRequirements'])->latest('start_date')->first();

        return Inertia::render('rostering/Dashboard', [
            'period' => $period === null ? null : [
                'identifier' => $period->identifier, 'title' => $period->title, 'start_date' => $period->start_date->toDateString(), 'end_date' => $period->end_date->toDateString(), 'status' => $period->status->value,
                'roster_day_count' => $period->days_count, 'doctor_requirement_count' => $period->doctor_requirements_count,
                'missing_staffing_count' => $period->days()->where('required_doctor_count', 0)->count(),
                'finding_count' => count($validator->handle($period)),
            ],
            'doctorCount' => Doctor::query()->where('active', true)->count(),
        ]);
    }
}
