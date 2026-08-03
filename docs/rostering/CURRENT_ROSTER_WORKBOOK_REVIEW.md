# Current Anaesthesia Roster Workbook Review

## Purpose and evidence policy

This review characterizes `Book1.xlsx` as supplied operational evidence before the Anaesthesia Rostering Foundation is implemented. It does not make the workbook canonical, approve an import mapping, or reproduce its visual structure in the application.

Reviewed source SHA-256: `98aa111fbfbe8706170f20a298317363e7d6ca6c5905412a7804b45d4be10c45`.

Finding labels mean:

- **Observed** — directly visible in workbook content, structure, or formulas.
- **Inferred** — a plausible interpretation supported by layout or repetition, but not confirmed by the department.
- **Requires confirmation** — a decision or meaning that cannot safely be established from the workbook.

## Workbook inventory

| Finding | Status | Evidence |
|---|---|---|
| The workbook contains one worksheet named `20.7.26-16.8.26`. | Observed | Workbook sheet inventory. |
| The date headers run from 20 July 2026 through 16 August 2026 inclusive. | Observed | Four seven-day header blocks in `B:AC`; Excel date values 46223–46250. |
| The displayed roster therefore covers 28 consecutive days / four weeks. | Observed | Header dates and weekday sequence. |
| The worksheet's stored dimension is `A1:BG2209`, but the populated operational roster is concentrated in `A1:AC89`, with notes and shift examples in approximately `AD:AH`. | Observed | Worksheet dimension and non-empty-cell inventory. Rows below the roster are largely formatting residue. |
| There are 1,122 non-empty cells and no formula cells. | Observed | OpenXML worksheet inspection found zero `<f>` formula elements. |
| The printed rendition spans 23 A4 portrait pages because one wide visual sheet is paginated horizontally and vertically. | Observed | Temporary PDF rendering of the unchanged workbook. |

## People and row organization

| Finding | Status | Evidence |
|---|---|---|
| Approximately 23 doctors are named. | Observed | 23 person-name labels in column A. Personal names are intentionally not repeated in this report. |
| Names generally use `family name, given name` presentation. | Observed | Column A identity labels. |
| The sheet separates at least two staff groupings: an `SMO/VMO` area and a `MEDREG` area. | Observed | Section headings in rows 1–5 and 46–49. |
| A doctor is not represented by one row. Person blocks range across multiple consecutive rows for identity, employee/FTE/hours information, work segments, breaks, call, overtime, leave, and notes. | Observed | Repeated multi-row blocks across rows 6–88. |
| Several early person blocks use a name row, a numeric identifier row, and a contracted-hours row. Later blocks use different shapes and sometimes role/FTE labels instead. | Observed | Column A patterns. |
| A normalized doctor record and separate dated assignments are required; row position cannot serve as identity. | Inferred | Variable block shapes and repeated date content. |

## Identity, employee numbers, and contracted time

| Finding | Status | Evidence |
|---|---|---|
| Explicit employee-like identifiers appear as six- or eight-digit numeric text, including values with leading zeroes. | Observed | Column A rows beneath several names. |
| Not every named doctor has an adjacent numeric identifier in the visible layout. | Observed | Later person blocks omit the three-row identity pattern. |
| `PN...` and one `PR...` reference occur in date cells and notes. They must not be assumed to be employee identifiers. | Observed / Requires confirmation | Examples include `PN` followed by eight digits; placement differs from explicit identity rows. |
| Contract notation includes `80hrs`, `80 Hours`, `40 Hours`, `0.75FTE`, and `0.5FTE`. | Observed | Column A and person metadata rows. |
| `5 Hours` also occurs inside dated roster cells and may describe a partial duty rather than an employment contract. | Observed / Inferred | Six dated cells use `5 Hours`. |
| The period represented by `80 Hours` or `40 Hours` is not stated. | Requires confirmation | No weekly, fortnightly, or four-week qualifier is present. |
| Contracted employment hours, their contract period, standard credited daily hours, and roster-period required hours must remain distinct. | Inferred | Mixed hours/FTE notation and four-week roster span. |

## Date and duty organization

| Finding | Status | Evidence |
|---|---|---|
| Column A contains identity/role information; `B:AC` contains 28 daily columns arranged in four seven-day blocks. | Observed | Headers and merged week bands. |
| Each week displays Monday through Sunday. | Observed | Weekday header rows. |
| Repeated daytime work is commonly split into `07:30-12:30 WRK` and `13:00-18:00 WRK`. | Observed | Each form occurs 139 times after whitespace normalization. |
| Another common day pattern is `07:30-12:00 WRK`, `12:00-12:30 UNBRK`, and `12:30-17:30 WRK`. | Observed | 92, 89, and 111 occurrences respectively. |
| Overnight work can be expressed across several consecutive rows: `22:00-03:00 WRK`, `03:00-03:30 UNBRK`, and `03:30-08:00 WRK`. | Observed | Each form occurs 19 times. |
| Other overnight components include `19:30-23:30 WRK`, `23:30-00:00 UNBRK`, `00:00-05:30 WRK`, and `05:30-08:00 PLAN_OT`. | Observed | Repeated dated cells. |
| A visually adjacent morning/break/afternoon chain may represent one daily duty, but the workbook does not establish whether it is one assignment or several independent assignments. | Inferred / Requires confirmation | Multi-row segments share a person/date column. |
| The foundation should preserve optional duty code, start time, end time, and credited hours while generating at most one counted daily assignment. | Inferred | Segment-aware compatibility without adopting the legacy row model. |

## Call, overtime, leave, and other codes

| Finding | Status | Evidence |
|---|---|---|
| `ONC_STD ONCALL` appears 28 times. | Observed | Dated roster cells. |
| A 24-hour notation `(07:30-07:30 24hours)` appears with on-call examples. | Observed | Dated and side-reference cells. |
| `2nd Call`, `overtime`, `PLAN_OT`, and `st-down` appear as distinct source terms. | Observed | Dated roster cells. |
| Leave-like codes include `REC`, `LSL`, `PDL`, `R/L`, `DCT`, and literal `Leave`; `sick` also appears. | Observed | Dated roster cells. |
| Other operational annotations include `TEACH PM`, `Exam 1`, `Exam 2`, `Exam 3`, `CME`, `M & M`, `QARTS`, `Half PM`, `AM`, `Post Nights`, and free-form teaching/committee text. | Observed | Dated roster cells and side notes. |
| Exact meanings, assignment prohibitions, and credited-hours effects cannot be safely derived from colour or abbreviation. | Requires confirmation | No authoritative legend or formulas define semantics. |

## Weekends, public holidays, notes, and exceptions

| Finding | Status | Evidence |
|---|---|---|
| Saturday and Sunday are explicit columns in every week. | Observed | Week headers. |
| Weekend cells contain a mixture of blank/coloured states, work segments, call, planned overtime, and leave-like codes. | Observed | Weekend columns `G:H`, `N:O`, `U:V`, `AB:AC`. |
| Weekday staffing numbers are present in the final roster row, while corresponding weekend cells are blank. | Observed | Row 89 contains values under selected Monday–Friday columns only. |
| `Ekka` appears on 12 August 2026 and is visually distinguished in the weekly heading area. | Observed | Fourth-week header/note band. |
| Whether `Ekka` is treated as a public holiday and whether public-holiday staffing differs are not stated. | Requires confirmation | No rule or formula accompanies the label. |
| Side notes include leave carry-over, dated leave requests, teaching/workshop commitments, school-return information, travel, and preference text such as “no calls if possible”. | Observed | Notes in and beyond column AD plus dated cells. |
| Notes mix hard constraints, preferences, history, and annotations in one visual surface. | Inferred | Free-form text has no typed classification. |

## Staffing counts and calculation behavior

| Finding | Status | Evidence |
|---|---|---|
| The final operational row contains static weekday numbers ranging from 2 to 8. | Observed | Row 89. |
| Those numbers are not formula results. | Observed | Workbook contains zero formula cells. |
| Their meaning could be required doctors, assigned doctors, theatre demand, vacancies, or another metric. | Requires confirmation | No row label or calculation defines them. |
| The workbook relies heavily on human interpretation, colour, placement, and manually entered values. | Inferred | Zero formulas, multi-row visual grammar, unlabelled count row, and free-form notes. |
| MVP staffing requirements must remain explicit administrator-authored values until the department confirms the count-row meaning. | Inferred | Prevents an unsupported import assumption. |

## Foundation consequences

The discovery supports a deliberately small but reversible foundation:

| Workbook concept | MVP support | Future seam | Requires confirmation |
|---|---|---|---|
| Doctor identity | Yes | Additional identifiers and staff grouping | Employee-number and `PN...` meanings |
| Contracted hours | Yes | Contract-period vocabulary | Yes |
| Roster-period required hours | Yes, explicit input | Derived targets after policy confirmation | Yes |
| Standard credited daily hours | Yes | Duty-specific credit rules | Yes |
| Daily assignment | Yes, one counted assignment | Optional time segments and duty mapping | Whether segments are independent |
| On-call | Not active | Duty-code seam | Meaning and credited hours |
| Leave/unavailable | Yes as explicit MVP prohibitions | Mapped leave categories | Which legacy codes prohibit assignment |
| Overtime | Not active | Duty-code seam | Credited/payroll effect |
| Weekend/public holiday | Ordinary roster dates | Day classification seam | Staffing and credit rules |
| Static bottom-row numbers | Not imported | Explicit staffing-requirement mapping | Meaning |
| Spreadsheet import | No | Characterization and mapping specification | All unresolved mappings |

## Scope verdict

**Mostly aligned.** The supplied roster supports the daily-assignment MVP and argues for a segment-aware data shape, but it does not justify segment-driven generation, payroll interpretation, on-call optimisation, or production import.

Recommended next slice after the foundation: **Requests and Availability**. Automatic generation should wait until the foundation and request semantics are stable.
