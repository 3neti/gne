# Anaesthesia Workbook-to-Domain Mapping

## Architectural rule

The legacy workbook combines identity, employment terms, availability, duty segments, leave, preferences, administrative notes, calendar semantics, and staffing figures in one visual surface. The application will normalize those concepts rather than copying sheet rows and colours.

## Proposed foundation mapping

| Workbook evidence | Foundation concept | Initial representation | Deferred refinement | Status |
|---|---|---|---|---|
| Person name in column A | `Doctor` | Stable application identifier, display name, active status | Staff grouping, additional identity fields | Observed evidence; mapping inferred |
| Six/eight-digit adjacent number | Doctor employee identifier | Optional text preserving leading zeroes | Identifier types and validation | Requires confirmation |
| `80 Hours`, `40 Hours` | Employment contracted hours | `contracted_hours` plus explicit `contracted_hours_period` | Derived period conversions | Requires confirmation |
| `0.75FTE`, `0.5FTE` | Employment fraction | Deferred or optional employment metadata | Relationship to contracted hours | Requires confirmation |
| Four-week header | `RosterPeriod` | Start date, end date, lifecycle status | Recurrence templates | Observed |
| Each calendar column | `RosterDay` | One date belonging to the roster period | Weekend/public-holiday classification | Observed |
| Final weekday numbers | `StaffingRequirement` | Explicit administrator-entered required-doctor count | Import mapping after meaning confirmed | Requires confirmation |
| Explicit period hours target | `DoctorRosterRequirement` | Doctor, roster period, required hours, source | Derivation from employment contract | Grounded MVP decision |
| Repeated work segments in one date | `RosterAssignment` | One counted doctor/date assignment with optional duty code, start/end, credited hours, notes | Multiple same-day duties or segment children | Requires confirmation |
| `WRK` | Assignment duty code | `standard_day` for clean authored MVP data only | Legacy mapping | Provisional |
| Leave/unavailable authored in application | Availability prohibition | `leave` and `unavailable` prevent generation | Multiple leave categories | Confirmed MVP rule, not legacy mapping |
| On-call, second call, overtime | Duty vocabulary seam | Retain documented future codes, no active rules | Call/credit/optimisation policies | Requires confirmation |
| Free-form notes and preferences | Request/availability evidence | Notes only in foundation | Typed requests, approvals, provenance | Deferred to Requests and Availability |
| Colours and row placement | No domain identity | Not imported | Mapping specification may use them as parsing hints | Observed but non-authoritative |
| `PN...` / `PR...` references | External/reference identifier | Not mapped | Typed source references | Requires confirmation |

## Foundation object shapes

These are architecture inputs, not implemented classes or migrations in this discovery phase.

### Doctor

- stable identifier
- display name
- employee identifier, nullable
- active status
- contracted hours, nullable
- contracted-hours period, explicit when hours exist
- standard daily hours

### RosterPeriod

- identifier
- start date
- end date
- lifecycle status

### RosterDay

- roster period
- date
- explicit staffing requirement
- later day classification seam

### DoctorRosterRequirement

- doctor
- roster period
- required hours
- source

For MVP, required hours are entered explicitly rather than derived from ambiguous `80 Hours`, `40 Hours`, or FTE notation.

### RosterAssignment

- doctor
- roster period
- date
- assignment status
- assignment source
- duty code, nullable; MVP default `standard_day`
- start time, nullable
- end time, nullable
- credited hours, nullable
- notes, nullable

Identity remains doctor + roster period + date for the one-counted-daily-assignment MVP. Optional segment-aware fields prevent an irreversible boolean model. Multiple same-day assignment identities remain deferred.

## Import boundary

Production workbook import is explicitly deferred. The next import-related artifact should be **Workbook Characterization and Mapping Specification**, covering:

1. reliable doctor-block recognition;
2. identity/reference distinction;
3. code authority and mappings;
4. segment grouping;
5. colour and note semantics;
6. staffing-count meaning;
7. validation and rejection behavior;
8. provenance and audit requirements.

Clean seed/demo records must use only confirmed MVP semantics. They must not be extracted automatically from this workbook.

## Compatibility assessment

| Workbook concept | MVP support | Future seam | Requires confirmation |
|---|---|---|---|
| Doctor identity | Yes | Identifier types and staff grouping | Employee-number meaning |
| Required hours | Yes, explicit per period | Contract-period derivation | Yes |
| Daily assignment | Yes | Optional time/duty fields | No for one-per-day MVP |
| Multiple work segments | Not active | Segment-aware assignment fields | Yes |
| On-call / second call | Not active | Duty-code vocabulary | Yes |
| Leave / unavailable | Yes for application-authored states | Legacy leave mapping | Yes |
| Overtime | Not active | Duty-code vocabulary | Yes |
| Weekend / holiday treatment | Calendar dates only | Day classification | Yes |
| Staffing count row | No import | Explicit requirement mapping | Yes |
| Notes/preferences | Notes only | Requests and Availability | Yes |
| Spreadsheet import | No | Mapping specification | Yes |

## GNE profile boundary

A future `anaesthesia-rostering` profile should contain only confirmed vocabulary and policies. Initial authored areas may cover active-doctor eligibility, roster boundaries, explicit staffing requirements, duplicate daily assignment prohibition, leave/unavailability prohibition, required-hours targets, and publication validation. Unconfirmed legacy codes must remain in the discovery register rather than canonical policies.
