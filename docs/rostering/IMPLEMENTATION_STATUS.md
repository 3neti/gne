# Anaesthesia Rostering Implementation Status

| Capability | Status | Evidence | Limitation |
| --- | --- | --- | --- |
| Doctors | Implemented | Routes, application actions, tests, Vue UI | No skills/subspecialties |
| Roster periods | Implemented | Transactional creation and inclusive day expansion | One department; no revisions |
| Staffing requirements | Implemented | Weekday/weekend defaults and date overrides | Headcount only |
| Required hours | Implemented | Explicit doctor-period targets | No contract derivation |
| Assignments | Implemented for manual roster | Preview/add/remove/move/replace services, grid, date and doctor projections | One standard-day assignment per doctor/date; no generator |
| Lifecycle | Manual roster subset | Draft → collecting requests → ready for generation → generated → under review | Publication remains deferred |
| Validation | Substantially implemented | Eligibility, staffing, hours, preference findings and CLI | No fairness solver, fatigue, specialist, or call rules |
| Audit | Implemented for major mutations | Structured append-only rows | Not event sourcing or compliance certification |
| Authorization | Implemented | Host roster-administrator policies and tests | No enterprise RBAC; ordinary users denied |
| GNE profile | Implemented skeleton | Vocabulary, lifecycle, policies, schemas, scenario, minimal deferred summary document | Operational rows are not repository artifacts |
| Requests | Implemented for administrators | Normalized dates, explicit status actions, audit and filters | No doctor portal or automatic approval |
| Availability | Implemented projection | 28-day matrix, doctor summaries, conflicts | No assignments or generation |
| Generation | Deferred | — | No generator |
| Publication | Deferred | — | No freeze/reopen |
| Import/export | Deferred | Characterization only | No production parser |
| OpenAPI | Deferred | Stable actions/identifiers only | Later slice |

The requests lifecycle scenario produces finalized JSON, a four-week visual calendar, doctor matrix, linked static HTML, and a Chromium PDF while rolling operational demonstration state back by default. Exclusive effective-state counts, provisional unspecified eligibility, overlap rejection, and daily eligible-pool readiness are implemented and tested.

The provisional unspecified-eligibility policy still requires department confirmation. Generation, multiple duties, optimization, and arbitrary YAML execution remain deferred.

The manual-roster lifecycle scenario produces 28 assigned dates, a doctor matrix, hours and staffing reports, deterministic validation, immutable revisions, audit evidence, self-contained HTML, and a Chromium PDF. It rolls operational demonstration state back by default and ends mandatory-valid with deliberate warnings. Manual creation is not automatic generation.
