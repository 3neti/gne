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
