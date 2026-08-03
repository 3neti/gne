# Anaesthesia Rostering Compass

**Product objective:** replace ambiguous spreadsheet structure with a small, trustworthy foundation for anaesthesia roster planning.

## Workbook grounding

The supplied four-week workbook proved the need to preserve doctor identity, contract notation, daily work, time/credit seams, staffing headcounts, and unresolved leave/call/overtime vocabulary. It contains no formulas and combines meaning through rows, colours, and free text, so it remains discovery evidence rather than runtime input.

## Current architecture and completed slice

Repository-authored profile policy describes the language. Host-owned Laravel records persist doctors, periods, days, explicit staffing and required hours, assignment seams, authorization, and audit evidence. Transactional application actions own mutations; Inertia pages are read/write delivery surfaces without business rules.

Implemented UI: rostering dashboard, doctors, period list/create/detail, daily staffing, doctor required hours, and assignment empty state. Implemented tests cover the central invariants and dependency exclusions.

## Known limitations and questions

One department and one simple administrator capability are assumed. Contracted-hour period meaning, employee-number semantics, holidays, staffing-row interpretation, duty-code mapping, call/overtime/leave effects, and multiple daily work segments remain unresolved. `off` is absence of assignment. There is no request model, roster grid, generator, publication, import/export, payroll, OpenAPI, or AI operation.

## Future slices

1. Requests and Availability.
2. Manual Roster Editing.
3. Roster Validation.
4. Draft Generation.
5. Publication.
6. OpenAPI AI Operation.

## Recommended next slice

**Requests and Availability** — confirm and model doctor-authored constraints before any generation work begins.
