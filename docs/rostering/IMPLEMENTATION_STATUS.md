# Anaesthesia Rostering Implementation Status

| Capability | Status | Evidence | Limitation |
| --- | --- | --- | --- |
| Doctors | Implemented | Routes, application actions, tests, Vue UI | No skills/subspecialties |
| Roster periods | Implemented | Transactional creation and inclusive day expansion | One department; no revisions |
| Staffing requirements | Implemented | Weekday/weekend defaults and date overrides | Headcount only |
| Required hours | Implemented | Explicit doctor-period targets | No contract derivation |
| Assignments | Foundation seam | Schema, service, uniqueness/integrity tests | No grid or generation |
| Lifecycle | Foundation subset | Draft → collecting requests → ready for generation | Later transitions declared only |
| Validation | Foundation subset | Deterministic error/warning findings and CLI | No fairness/fatigue/coverage rules |
| Audit | Implemented for major mutations | Structured append-only rows | Not event sourcing or compliance certification |
| Authorization | Implemented | Host roster-administrator policies and tests | No enterprise RBAC; ordinary users denied |
| GNE profile | Implemented skeleton | Vocabulary, lifecycle, policies, schemas, scenario, minimal deferred summary document | Operational rows are not repository artifacts |
| Requests | Deferred | — | Next slice |
| Generation | Deferred | — | No generator |
| Publication | Deferred | — | No freeze/reopen |
| Import/export | Deferred | Characterization only | No production parser |
| OpenAPI | Deferred | Stable actions/identifiers only | Later slice |

The foundation integrity closure adds error-only readiness blocking, transactional assignment audit, domain duplicate rejection, a closed period-update allowlist, and the rollback-by-default `ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE` proof. Missing active-doctor targets and zero staffing remain warnings. Requests, availability, generation, multiple duties, optimization, and arbitrary YAML execution remain deferred.
