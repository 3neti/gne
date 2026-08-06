# Policy Compatibility Matrix

Required Hours Meaning and Target Hours Enforcement are orthogonal but not every combination is coherent.

| Required-hours meaning | Informational | Soft warning | Hard minimum | Hard maximum |
|---|---:|---:|---:|---:|
| Roster-period clinical-duty target | Compatible | Compatible | Compatible | Compatible |
| Roster-period minimum obligation | Compatible | Compatible | Compatible | Conflict |
| Planning reference only | Compatible | Compatible | Conflict | Conflict |

This produces nine compatible and three conflicting combinations. A minimum obligation paired with a hard maximum assigns contradictory authority to the same value. A planning reference cannot truthfully be a hard bound. Unknown meanings or enforcement modes fail closed.

The PHP implementation is `RequiredHoursPolicyCompatibilityMatrix`; the scenario artifact renders the same ordered entries. Changes require an authored grammar revision, tests, documentation, and department review.
