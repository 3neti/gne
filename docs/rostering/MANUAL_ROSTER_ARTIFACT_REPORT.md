# Manual Roster Artifact Report

The manual-roster artifact is a disposable projection of one finalized rollback-isolated scenario result. It is not canonical business source. Its root is `.gne/reports/rostering/manual-roster/`.

- `report.json` — complete calendar, matrix, assignments, doctor hours, validation, proofs, revisions, and audit.
- `html/index.html` — linked report entrypoint.
- `html/roster-calendar.html` — four weekly assigned calendar bands.
- `html/doctor-matrix.html` — doctor-by-date assignment and availability overlays.
- `html/doctor-hours.html` — required, assigned, variance, dates, and status.
- `html/staffing.html` — daily requirement, count, variance, and finding summary.
- `html/validation.html` — structured errors and warnings.
- `html/revisions.html` — immutable revision and audit history.
- `pdf/anaesthesia-manual-roster.pdf` — printable landscape rendition.

Every HTML page uses only a shared relative stylesheet and has no remote dependency. The PDF prominently says “Anaesthesia Department Draft Roster” and “Manually Authored Draft — Not Yet Published.” It contains actual assigned doctors and never says that no assignments were generated. Checksums and page counts are recorded in the final slice report after artifact generation and visual inspection.
