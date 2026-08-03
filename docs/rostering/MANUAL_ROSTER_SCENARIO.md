# Manual Roster Scenario

`ANAESTHESIA-MANUAL-ROSTER` is a repository-authored, operation-allowlisted lifecycle proof. Run it with `php artisan gne:roster:lifecycle:run --scenario=ANAESTHESIA-MANUAL-ROSTER --artifact --json`.

The scenario creates ten fictional doctors and a 28-day September 2026 period. Weekdays require seven doctors and weekends five. It records accepted leave and unavailability, proves both prohibit assignment, rejects a duplicate, previews without persistence, creates a fully staffed manual schedule, moves and replaces assignments, removes one to expose understaffing, repairs it, and adds one deliberate overstaffing and preferred-off warning.

The final snapshot has 181 assignments: 27 fully staffed dates and one overstaffed date. It is `generated` and `valid_with_warnings`, with no mandatory errors. Every committed mutation uses the production application service and creates exactly one revision. Operational rows roll back by default; finalized report data survives in the returned result and optional artifacts. `--keep-state` is explicit and intended only for controlled development demonstrations.

This scenario contains no assignment generator, optimization heuristic, workbook import, publication, payroll, on-call interpretation, or arbitrary service execution from YAML.

The isolation proof also creates `ROSTER-2026-09` and one unrelated period audit inside the same rollback boundary. The returned report is built only through the exact-period audit service for `ROSTER-MANUAL-SCENARIO-2026-09`; metadata records the scoped count and requires `unrelated_identifiers_present: 0`. The JSON contains complete revisions and audits, plus deterministic recent and action-summary projections.
