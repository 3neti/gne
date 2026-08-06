# Anaesthesia Rostering Compass

Initial draft generation is a deterministic, explainable, replaceable balanced-greedy computation. It requires valid readiness inputs and an empty roster; generated output remains manually editable.

**Product objective:** replace ambiguous spreadsheet structure with a small, trustworthy foundation for anaesthesia roster planning.

## Workbook grounding

The supplied four-week workbook proved the need to preserve doctor identity, contract notation, daily work, time/credit seams, staffing headcounts, and unresolved leave/call/overtime vocabulary. It contains no formulas and combines meaning through rows, colours, and free text, so it remains discovery evidence rather than runtime input.

## Current architecture and completed slice

Repository-authored profile policy describes the language. Host-owned Laravel records persist doctors, periods, days, explicit staffing and required hours, assignment seams, authorization, and audit evidence. Transactional application actions own mutations; Inertia pages are read/write delivery surfaces without business rules.

Implemented UI: rostering dashboard, doctors, period list/create/detail, daily staffing, doctor required hours, availability, and a real manual assignment calendar/matrix with add, remove, move, replace, preview, validation, hours, exact-period revision summary, audit summary, and period-owned complete history. Deterministic generation now calculates pre-proposal feasibility and revision-1 quality, preserves selected/next-best ranking facts, and distinguishes raw, structural, and residual hours variance. Human roster UI and artifacts show names while machine JSON retains identifiers.

Foundation integrity is now executable through the allowlisted repository scenario. Readiness preserves warnings while blocking errors; assignment creation and its safe audit evidence commit or roll back together; period edits cannot bypass lifecycle transitions.

## Known limitations and questions

One department and one simple administrator capability are assumed. Unspecified-doctor eligibility remains a provisional policy requiring department confirmation. Contracted-hour period meaning, employee-number semantics, holidays, staffing-row interpretation, call/overtime rules, and multiple daily work segments remain unresolved. `off` is absence of assignment. The active duty vocabulary is only `standard_day`. Generation is deterministic greedy and initial-only; there is no publication, import/export, payroll, OpenAPI, doctor self-service, fatigue enforcement, or AI operation.

## Future slices

1. Requests and Availability.
2. Manual Roster Editing.
3. Roster Validation.
4. Draft Generation.
5. Publication.
6. OpenAPI AI Operation.

## Current slice

Administrator-entered requests and availability are implemented as audited host state. Accepted requests compile into exclusive effective per-doctor/date states; explicit availability remains evidence, while unspecified active doctors are provisionally eligible. Daily eligible-pool shortages and hard conflicts block readiness; preferences remain overlays and soft conflicts remain warnings. The UI and repository lifecycle proof expose four weekly calendar bands and a doctor matrix through rollback-isolated JSON, HTML, and PDF evidence without generating assignments.

## Recommended next slice

**Calibrated Generation and Validation Enforcement** — use actual confirmed, effective department choices to harden generation and validation before publication.
# Policy calibration boundary

Department decision -> authored/calibrated policy -> `ResolvedRosterPolicy` -> generation, validation, quality, and explanations. Safe provisional choices remain visible. No optimizer, publication, or workbook inference is introduced.

Resolution is date-aware and snapshot-safe. Future confirmation does not alter the current fingerprint; roster generation evaluates the period start; validation hydrates its persisted resolved-policy snapshot. Department controls use closed choices with explicit authority and effective dates.

Typed parameter schemas belong to repository policy declarations. Runtime confirmation fails closed for missing, invalid, unresolved, or unsupported choices. New revisions permanently supersede predecessors, and post-expiry resolution never silently restores old policy.
