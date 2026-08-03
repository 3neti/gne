# Anaesthesia Rostering Functional Specification

The numbered requirements below preserve the approved FR-001–FR-014 handoff vocabulary. “Foundation subset” means only structural capabilities expressly implemented in this slice.

| Requirement | Foundation status | Implemented evidence | Deferred remainder |
| --- | --- | --- | --- |
| FR-001 — Doctor management | Implemented | Stable identifiers, contract notation, active/inactive history, administrator UI/actions/tests | Skills, subspecialties, credentialing |
| FR-002 — Roster-period management | Implemented | Stable period, inclusive day expansion, explicit early lifecycle | Revisions, reopen, publication |
| FR-003 — Staffing and required-hours inputs | Implemented | Weekday/weekend defaults, date overrides, explicit doctor-period targets | Demand/contract derivation |
| FR-004 — Requests and availability | Deferred | — | Next slice |
| FR-005 — Leave handling | Deferred | Assignment vocabulary seam only | Request workflow and legacy mapping |
| FR-006 — Preferences | Deferred | — | Semantics and policy |
| FR-007 — On-call and specialist coverage | Deferred | — | Department confirmation |
| FR-008 — Roster construction | Partially implemented | One counted primary assignment persistence seam and constraints | Grid, manual editing, generation, multiple duties |
| FR-009 — Validation and fairness | Deferred except foundation subset | Structural validation findings | Fatigue, fairness, preferences, coverage |
| FR-010 — Review and approval | Deferred | Lifecycle vocabulary only | Review UI and authority |
| FR-011 — Publication | Deferred | Status/policy vocabulary only | Freeze, release, reopen |
| FR-012 — Import and export | Deferred | Workbook characterization only | Confirmed mapping, CSV/XLSX |
| FR-013 — Reporting and analytics | Deferred | Foundation counts only | Hours variance, payroll, SLA |
| FR-014 — Integration and automation | Partially implemented | Stable application actions and public identifiers | OpenAPI, AI operation, generator interface |

## Foundation user journeys

A roster administrator registers and maintains fictional or real doctor records; creates a bounded roster period with explicit weekday/weekend headcount defaults; reviews every created day; overrides individual dates; authors one required-hours target per doctor; advances through the two enabled early transitions; and inspects deterministic structural findings and audit evidence. An ordinary authenticated user has no rostering access.

No action in this specification generates a roster, imports the workbook, executes an assignment, calculates pay, or publishes a schedule.
