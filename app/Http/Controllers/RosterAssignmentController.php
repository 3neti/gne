<?php

namespace App\Http\Controllers;

use App\Models\RosterPeriod;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterAssignmentController extends Controller
{
    public function index(RosterPeriod $rosterPeriod): Response
    {
        Gate::authorize('view', $rosterPeriod);

        return Inertia::render('rostering/periods/Assignments', ['period' => ['identifier' => $rosterPeriod->identifier, 'title' => $rosterPeriod->title], 'assignmentCount' => $rosterPeriod->assignments()->count()]);
    }
}
