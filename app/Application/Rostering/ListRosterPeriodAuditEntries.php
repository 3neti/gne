<?php

namespace App\Application\Rostering;

use App\Models\RosterAuditEntry;
use App\Models\RosterPeriod;
use Illuminate\Database\Eloquent\Collection;

final class ListRosterPeriodAuditEntries
{
    /**
     * @param  list<string>|null  $actions
     * @return Collection<int, RosterAuditEntry>
     */
    public function handle(RosterPeriod $rosterPeriod, ?array $actions = null, ?int $limit = null, bool $latestFirst = false): Collection
    {
        $query = RosterAuditEntry::query()
            ->whereBelongsTo($rosterPeriod)
            ->when($actions !== null, fn ($query) => $query->whereIn('action', $actions));

        $latestFirst
            ? $query->orderByDesc('created_at')->orderByDesc('id')
            : $query->orderBy('created_at')->orderBy('id');

        return $query->when($limit !== null, fn ($query) => $query->limit($limit))->get();
    }
}
