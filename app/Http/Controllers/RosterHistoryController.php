<?php

namespace App\Http\Controllers;

use App\Application\Rostering\ListRosterPeriodAuditEntries;
use App\Models\RosterPeriod;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RosterHistoryController extends Controller
{
    public function revisions(RosterPeriod $rosterPeriod): Response
    {
        Gate::authorize('view', $rosterPeriod);

        return Inertia::render('rostering/periods/History', [
            'period' => $this->period($rosterPeriod),
            'section' => 'revisions',
            'entries' => $rosterPeriod->revisions()->with('changes')->orderByDesc('revision_number')->get()->map(fn ($revision): array => ['identifier' => $revision->identifier, 'revision_number' => $revision->revision_number, 'reason' => $revision->reason, 'summary' => $revision->summary, 'validation_status' => $revision->validation_status, 'created_at' => $revision->created_at?->toIso8601String(), 'changes' => $revision->changes->count()])->all(),
        ]);
    }

    public function audit(RosterPeriod $rosterPeriod, ListRosterPeriodAuditEntries $listAudit): Response
    {
        Gate::authorize('view', $rosterPeriod);

        return Inertia::render('rostering/periods/History', [
            'period' => $this->period($rosterPeriod),
            'section' => 'audit',
            'entries' => $listAudit->handle($rosterPeriod, latestFirst: true)->map(fn ($entry): array => ['action' => $entry->action, 'entity_type' => $entry->entity_type, 'entity_identifier' => $entry->entity_identifier, 'reason' => $entry->reason, 'created_at' => $entry->created_at?->toIso8601String()])->all(),
        ]);
    }

    /** @return array{identifier: string, title: string} */
    private function period(RosterPeriod $rosterPeriod): array
    {
        return ['identifier' => $rosterPeriod->identifier, 'title' => $rosterPeriod->title];
    }
}
