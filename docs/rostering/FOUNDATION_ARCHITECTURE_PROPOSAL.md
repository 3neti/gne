# Anaesthesia Rostering Foundation Architecture Proposal

Status: approved implementation basis for the foundation vertical slice

Baseline: `9d7446a87702eb05272a4a1de34d726f563b4e58`

Workbook role: discovery evidence only

## 1. Domain entities

The operational host application owns six focused records:

- `Doctor`: stable clinician identity and contract notation.
- `RosterPeriod`: one bounded planning window and its explicit lifecycle.
- `RosterDay`: one calendar date inside a period and its authored headcount requirement.
- `DoctorRosterRequirement`: an explicit required-hours target for one doctor in one period.
- `RosterAssignment`: the future manual-roster persistence seam, limited to one counted primary assignment per doctor and day.
- `RosterAuditEntry`: append-only evidence of major host mutations.

These records are not GNE Compilation Subjects. They are operational state whose rules are described by the repository-authored anaesthesia-rostering profile.

## 2. Identifiers

Numeric database keys remain internal. Public route binding and audit references use stable text identifiers:

- doctors: `DOCTOR-` plus a zero-padded sequence;
- periods: administrator-authored `ROSTER-YYYY-MM`-style identifiers, validated but not inferred from dates;
- assignments: `ASSIGNMENT-` plus an immutable ULID.

Identifiers never depend on workbook row position. Employee identifiers are optional strings so leading zeroes survive.

## 3. Invariants

- A doctor has a non-empty name and positive standard daily hours.
- Contracted hours and their period are either both present or both absent.
- A period ends on or after it starts and begins in `draft`.
- Period creation atomically creates every inclusive calendar day exactly once.
- Weekend classification is deterministic; public-holiday classification is an editable seam.
- Every stored daily staffing requirement is explicit and non-negative.
- A doctor has at most one required-hours record per period; its non-negative value and source are explicit.
- A doctor has at most one counted primary assignment per period day.
- Assignment doctor, period, and day must agree; inactive doctors cannot receive new assignments.
- Lifecycle changes use the declared transition map, never arbitrary status editing.
- Material mutations append an audit entry in the same transaction.

## 4. Database tables

| Table | Purpose | Principal constraints |
| --- | --- | --- |
| `doctors` | Host doctor records | unique identifier; optional unique employee identifier; positive hours enforced by services/input validation |
| `roster_periods` | Planning windows | unique identifier; indexed status/dates; creator FK |
| `roster_days` | Inclusive period dates | unique `(roster_period_id, date)`; indexed date/type |
| `doctor_roster_requirements` | Explicit period targets | unique `(doctor_id, roster_period_id)`; indexed source |
| `roster_assignments` | Primary daily assignment seam | unique `(doctor_id, roster_period_id, roster_day_id)`; stable identifier |
| `roster_audit_entries` | Mutation evidence | actor FK nullable; indexed entity/action/time; JSON previous/new values |

No revision ledger or generic event store is introduced. Published mutation and reopening remain deferred.

## 5. Relationships

- A user creates many roster periods and audit entries.
- A roster period owns days, requirements, and assignments.
- A doctor owns requirements and assignments and remains historically visible when inactive.
- A roster day belongs to one period and owns assignments.
- Audit entries refer to entities by type and stable identifier rather than polymorphic database foreign keys.

Controllers eager-load the relationships needed by each page; Vue receives prepared arrays and performs no domain queries or policy evaluation.

## 6. Lifecycle

The complete vocabulary is `draft`, `collecting_requests`, `ready_for_generation`, `generated`, `under_review`, `published`, and `archived`. The foundation operationally permits only:

```text
draft → collecting_requests → ready_for_generation
```

Later transitions are declared in repository policy but unavailable until their preconditions and operations exist. Reopening `published → under_review` is documented only. Period dates become immutable once dependent activity exists; the foundation update action permits notes/title changes but not boundary changes.

## 7. GNE profile structure

`business/profiles/anaesthesia-rostering` owns the vocabulary, lifecycle declaration, focused policies, JSON Schemas, and create-period scenario. It contains no document definitions and no unresolved workbook codes. The profile describes business policy; Eloquent remains a rebuildable-independent host operational store rather than repository evidence projection.

A monolithic `ResolvedRosterPolicy` is deferred. Focused enum/value services implement only the rules the foundation executes, while YAML preserves policy provenance and the future resolution seam.

## 8. Authorization model

Rostering uses a host-owned capability separate from Compilation Subject grants:

- `is_roster_administrator = true`: view and mutate roster foundation records;
- ordinary verified user: no rostering access in this foundation.

Laravel gates and model policies enforce every route/action server-side. UI permissions are descriptive props only. This small capability can later evolve without forcing roster records into repository-subject authorization.

## 9. Audit model

Each major application action writes a structured `RosterAuditEntry` transactionally with actor, action, entity type/identifier, previous and new JSON-compatible values, optional reason, and timestamp. Events covered are doctor create/update/deactivate, period create/transition, staffing changes, and requirement create/update. Secrets and session state are never recorded.

## 10. UI map

- `/rostering`: latest-period dashboard and completeness summary.
- `/rostering/doctors`: active/inactive list and create form.
- `/rostering/doctors/{doctor}/edit`: update and deactivate.
- `/rostering/periods`: period list and create form.
- `/rostering/periods/{rosterPeriod}`: overview and valid early transition.
- `/rostering/periods/{rosterPeriod}/staffing`: per-date requirements plus weekday/weekend bulk defaults.
- `/rostering/periods/{rosterPeriod}/requirements`: explicit doctor targets.
- `/rostering/periods/{rosterPeriod}/assignments`: read-only foundation seam and deferred-editor empty state.

All links/forms use Wayfinder-generated typed routes. Pages use the existing Inertia layout, accessible labels, responsive tables, server-provided permissions, and no roster rule computation.

## 11. API deferrals

No OpenAPI or mutation API is implemented. Application actions and stable identifiers intentionally support later task-oriented endpoints without exposing numeric IDs.

## 12. Import deferrals

There is no production workbook parser, import command, mapping automation, or seed extraction. The workbook characterization and compatibility documents remain the only bridge. Any future importer requires a confirmed mapping specification and explicit provenance.

## 13. Future compatibility seams

- Assignment duty code, times, credited hours, and notes preserve a narrow segment-refinement seam.
- Day type allows confirmed public-holiday policy later.
- Requirement source allows future contract/policy derivation without claiming it now.
- Stable application actions can back a future OpenAPI surface.
- Focused policy declarations can later resolve into a `ResolvedRosterPolicy`.
- A future `RosterGenerator` boundary may consume a period and resolved policy, but no interface or implementation is added now.

Requests, multiple duties, optimization, publication, payroll, x-change, and x-document remain absent.

## 14. Acceptance tests

Pest feature/unit/architecture tests cover doctor invariants and authorization; atomic period/day creation; weekend/default requirements; date overrides and bulk changes; explicit doctor targets; assignment uniqueness/coherence; early transitions; deterministic validation findings; transactional audit records; profile validation; and forbidden dependencies/features.

## 15. Risks and assumptions

- Contracted-hours notation remains descriptive; no FTE/period conversion occurs.
- One primary assignment cannot represent split shifts or call duties; that is an explicit MVP limit.
- `off` is absence of assignment and cannot distinguish deliberate off-duty intent.
- Staffing is headcount only; skills, theatre demand, and call coverage are absent.
- Public holidays are manual because no jurisdiction/calendar is confirmed.
- Lifecycle completeness currently checks structural foundation inputs, not requests, fairness, fatigue, or generation readiness.
- One boolean administrator capability is intentionally not enterprise RBAC.
- SQLite tests and database constraints protect identity; authoritative cross-record invariants remain in transactional application services.
- Open department questions remain recorded in `OPEN_QUESTIONS_FOR_DEPARTMENT.md` and do not justify inventing workbook semantics.
