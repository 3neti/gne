# Manual Roster Artifact Report

The manual-roster artifact is a disposable projection of one finalized rollback-isolated scenario result. It is not canonical business source. Its root is `.gne/reports/rostering/manual-roster/`.

- `report.json` — complete calendar, matrix, assignments, doctor hours, validation, proofs, revisions, and audit.
- `html/index.html` — linked report entrypoint.
- `html/roster-calendar.html` — four weekly assigned calendar bands.
- `html/doctor-matrix.html` — doctor-by-date assignment and availability overlays.
- `html/doctor-hours.html` — required, assigned, variance, dates, and status.
- `html/staffing.html` — daily requirement, count, variance, and finding summary.
- `html/validation.html` — structured errors and warnings.
- `html/revisions.html` — complete immutable revision history.
- `html/audit.html` — complete exact-period audit history grouped for review.
- `pdf/anaesthesia-manual-roster.pdf` — printable landscape rendition.

Every HTML page uses only a shared relative stylesheet and has no remote dependency. The PDF prominently says “Anaesthesia Department Draft Roster” and “Manually Authored Draft — Not Yet Published.” Its history section contains current/total revision facts, mutation and audit counts, latest revisions, and recent/notable evidence instead of printing every raw row. Complete evidence remains in JSON and detailed HTML. Isolation metadata names the selected period and proves zero unrelated identifiers. Checksums and page counts are recorded in the final slice report after artifact generation and visual inspection.
