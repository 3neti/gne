<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roster_audit_entries')->whereNull('roster_period_id')->orderBy('id')->eachById(function (object $entry): void {
            $rosterPeriodId = $this->resolveRosterPeriodId($entry);

            if ($rosterPeriodId !== null) {
                DB::table('roster_audit_entries')->where('id', $entry->id)->update(['roster_period_id' => $rosterPeriodId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roster_audit_entries')->update(['roster_period_id' => null]);
    }

    private function resolveRosterPeriodId(object $entry): ?int
    {
        $directTable = match ($entry->entity_type) {
            'roster_period' => ['roster_periods', 'identifier'],
            'doctor_schedule_request' => ['doctor_schedule_requests', 'identifier'],
            'roster_assignment' => ['roster_assignments', 'identifier'],
            'roster_revision' => ['roster_revisions', 'identifier'],
            default => null,
        };

        if ($directTable !== null) {
            [$table, $identifierColumn] = $directTable;
            $query = DB::table($table)->where($identifierColumn, $entry->entity_identifier);
            $periodId = $table === 'roster_periods' ? $query->value('id') : $query->value('roster_period_id');

            if (is_numeric($periodId)) {
                return (int) $periodId;
            }
        }

        foreach ([$entry->new_value, $entry->previous_value] as $encodedValue) {
            if (! is_string($encodedValue)) {
                continue;
            }

            $value = json_decode($encodedValue, true);
            if (is_array($value) && isset($value['roster_period_id']) && is_numeric($value['roster_period_id'])) {
                return (int) $value['roster_period_id'];
            }
        }

        return null;
    }
};
