# Manual Roster Editing

Manual roster editing turns resolved availability inputs into deliberate administrator assignments. Availability, leave, unavailability, and preferences remain request evidence; only an assignment mutation creates a roster fact.

## Mutation pipeline

```text
administrator command
→ request and lifecycle validation
→ rolled-back preview
→ atomic assignment mutation
→ roster-wide validation snapshot
→ one immutable revision and structured change
→ assignment and revision audit
→ calendar, matrix, staffing, and hours projections
```

The active assignment grammar is intentionally narrow: status `assigned`, source `manually_added` or `manually_changed`, duty `standard_day`, positive credited hours, optional same-day start/end times, and optional notes. One counted assignment is permitted for a doctor/date/period. The database unique constraint and domain exception both protect that identity.

Create, move, and replace reject inactive doctors, dates outside the period, duplicates, accepted leave, accepted unavailability, non-positive hours, malformed timing, and periods outside `ready_for_generation`, `generated`, or `under_review`. Remove is allowed in editable states even when the resulting draft is understaffed. The first committed assignment transitions the period to `generated`; this means a roster body exists and does not claim automatic generation.

`ValidateRoster` reports understaffing as an error. Overstaffing, below/above target hours, and unhonoured preferred-off requests are warnings while draft or under review. The browser consumes only `BuildRosterCalendar` and `BuildDoctorHoursSummary`; it does not calculate business totals.

Only roster administrators may view full manual roster, revision, and audit surfaces or submit/preview mutations. Publication, automatic generation, optimization, overnight duties, multiple daily segments, payroll, doctor self-service, and overrides are deferred.

Every period-owned audit record stores the exact `roster_period_id` within the mutation transaction. `ListRosterPeriodAuditEntries` is the authoritative read boundary for the roster page, complete history, scenarios, and artifacts. It excludes other periods and unscoped doctor administration without searching JSON, parsing identifiers, or filtering only by action. Historical rows that cannot be deterministically backfilled remain unscoped and never enter a period history.

The main roster page shows current revision, total revisions, latest reason, roster state after revision, action counts, and the latest 20 revision/audit entries. Complete period-owned history remains available from `/rostering/periods/{period}/revisions` and `/rostering/periods/{period}/audit`. A revision is a business-level roster version; an audit entry is operational evidence that an action occurred.

Future draft generation must create one top-level roster revision with many change records, one generation audit, and one final validation snapshot. It must not invoke the manual assignment command repeatedly in a way that creates one top-level revision per generated row. No generator exists yet.
