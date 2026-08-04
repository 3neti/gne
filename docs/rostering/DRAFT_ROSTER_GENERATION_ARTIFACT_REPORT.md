# Draft Roster Generation Artifact Report

`.gne/reports/rostering/draft-generation` contains complete JSON, linked static HTML report pages, and an operator-focused PDF. It records generator and policy identity, direct-input fingerprints, feasibility, staffing, structural/residual hours balance, weekend distribution, availability use, preferences, selected-over explanations, validation, the generation revision and audit, and the later manual correction.

`generated-calendar.html` and `current-roster-calendar.html` show assigned doctor identity per date for revision 1 and revision 2 respectively. `assignments-by-date.html` is the complete operator-readable list. Four `doctor-matrix-week-*.html` files show all ten doctors across all 28 dates using `G`, `M`, `L`, `U`, `PW`, `PO`, and finding markers. These representations are included in the primary PDF. `staffing.html` remains a count summary and is not a roster calendar.

`feasibility.html`, `quality-summary.html`, `hours-balance.html`, `weekend-balance.html`, `availability-use.html`, `preference-analysis.html`, and `explanations.html` provide the quality proof. Primary HTML and PDF use names without visible doctor IDs; `report.json` remains complete machine evidence with identifiers and ranking reason codes.
