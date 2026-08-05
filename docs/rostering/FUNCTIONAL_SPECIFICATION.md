# Anaesthesia Rostering Functional Specification

The numbered requirements below preserve the approved FR-001–FR-014 handoff vocabulary. “Foundation subset” means only structural capabilities expressly implemented in this slice.

| Requirement | Foundation status | Implemented evidence | Deferred remainder |
| --- | --- | --- | --- |
| FR-001 — Doctor management | Implemented | Stable identifiers, contract notation, active/inactive history, administrator UI/actions/tests | Skills, subspecialties, credentialing |
| FR-002 — Roster-period management | Implemented | Stable period, inclusive day expansion, explicit early lifecycle | Revisions, reopen, publication |
| FR-003 — Staffing and required-hours inputs | Implemented | Weekday/weekend defaults, date overrides, explicit doctor-period targets | Demand/contract derivation |
| FR-004 — Requests and availability | Implemented for administrator entry | Normalized dates, explicit statuses, conflict validation, availability calendar | Doctor self-service deferred |
| FR-005 — Leave handling and draft generation | Partially implemented | Accepted leave/unavailability are hard filters; deterministic generation consumes explicit provisional or confirmed policy | Final department policy calibration, doctor self-service, legacy mapping, optimization |
| FR-006 — Preferences and generation results | Implemented | Preview exposes policy and feasibility diagnostics, balance, weekend, preference, availability-use, findings, ranking traces, and fingerprint | Mathematical optimization |
| FR-007 — On-call and specialist coverage | Deferred | — | Department confirmation |
| FR-008 — Roster construction and validation | Implemented for supported calibrated policies | Common resolved policy governs unspecified eligibility and structural-allocation validation; preview, generation and manual operations remain supported | Multiple duties and unconfirmed advanced rules |
| FR-009 — Validation, fairness, and explanations | Implemented | Explanations cite governing policy and provenance; unresolved allocation returns policy calibration required instead of a fairness claim | Optimal fairness, fatigue, specialist coverage |
| FR-010 — Review and approval | Deferred | Lifecycle vocabulary only | Review UI and authority |
| FR-011 — Publication | Deferred | Status/policy vocabulary only | Freeze, release, reopen |
| FR-012 — Import and export | Deferred | Workbook characterization only | Confirmed mapping, CSV/XLSX |
| FR-013 — Reporting and analytics | Manual and generated roster reporting implemented | Assigned calendar, matrix, staffing, target-adjusted hours, weekends, availability use, preferences, concise operator history, complete JSON/HTML/PDF evidence | Payroll, SLA, operational analytics |
| FR-014 — Integration and automation | Generation-run and exact-period audit subset implemented | One run, one batch revision, many changes, one generation audit; stable period scope | Enterprise certification, OpenAPI, AI operation |

## Foundation user journeys

A roster administrator registers and maintains fictional or real doctor records; creates a bounded roster period with explicit weekday/weekend headcount defaults; reviews every created day; overrides individual dates; authors one required-hours target per doctor; advances through the two enabled early transitions; and inspects deterministic structural findings and audit evidence. An ordinary authenticated user has no rostering access.

No action in this specification automatically generates a roster, imports the workbook, calculates pay, or publishes a schedule. Authorised administrators now deliberately create and revise assignments.

Manual revision validation records the state of the whole roster immediately after the command, not whether that command was itself invalid. Manual actions create one top-level revision each. A future generation command must create one top-level revision containing many assignment changes; generation remains deferred.

Foundation readiness permits warning findings and rejects error findings. Assignment acceptance includes immediate allowlisted audit evidence in the same transaction. Period updates accept only title and notes; status changes use the transition service. The lifecycle scenario runner demonstrates these rules without adding production workflow behavior.

For requests and availability, accepted leave and unavailability are hard blocks; accepted availability is positive evidence but not permission; preferences are soft overlays. Until department policy is confirmed, an active doctor with no effective request remains provisionally eligible as `unspecified`. Readiness requires the resulting eligible pool to meet each day's explicit staffing demand and still makes no claim that generation will find a valid or balanced roster. Equivalent accepted requests may not overlap on any effective date.

Roster policy resolution always receives an evaluation context. A confirmed revision applies only when its inclusive effective window contains the evaluation date. The current screen uses today; generation and preview use roster-period start; generated-run validation reuses the stored snapshot. Confirmation requires a registered option, authority, source reference, notes, and valid dates. An earlier window is atomically closed when a later decision supersedes it; ambiguous overlaps are rejected.
