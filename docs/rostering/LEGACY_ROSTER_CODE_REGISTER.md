# Legacy Anaesthesia Roster Code Register

This is a discovery register, not canonical vocabulary. Every mapping is provisional until confirmed by the department. Source spellings are preserved exactly enough to recognize workbook evidence; whitespace variants are normalized for readability.

| Observed source text | Provisional normalized code | Provisional meaning | Scheduling effect | Credited-hours effect | Confirmation status |
|---|---|---|---|---|---|
| `WRK` with time range | `standard_day` or `worked_segment` | Ordinary worked time segment | Counts as work; whether several segments form one assignment is unresolved | Derivable duration is visible, but credit rule is unconfirmed | Requires confirmation |
| `UNBRK` with time range | `unbroken_segment` | Unbroken/meal-break-related segment | Appears between work segments; do not generate independently | Unknown | Requires confirmation |
| `ONC_STD ONCALL` | `on_call` | Standard on-call duty | Later duty-code seam only | Unknown, including 24-hour credit | Requires confirmation |
| `(07:30-07:30 24hours)` | `on_call_24_hour_notation` | Time annotation associated with on-call | Annotation until mapped | Unknown | Requires confirmation |
| `2nd Call` | `second_call` | Secondary call responsibility | Later duty-code seam only | Unknown | Requires confirmation |
| `overtime` | `overtime` | Overtime annotation/duty | Not active in MVP rules | Unknown | Requires confirmation |
| `PLAN_OT` | `planned_overtime` | Planned overtime segment | Not active in MVP rules | Visible time duration, policy unknown | Requires confirmation |
| `st-down` | `stand_down` | Possible post-duty stand-down | Do not infer availability or leave | Unknown | Requires confirmation |
| `Post Nights` | `post_nights` | Possible post-night recovery/stand-down | Do not infer prohibition | Unknown | Requires confirmation |
| `REC` | `recovery_leave` | Possibly recreation or recovery leave | Do not map automatically to MVP leave | Unknown | Requires confirmation |
| `LSL` | `long_service_leave` | Long-service leave | Likely prevents standard assignment | Unknown | Requires confirmation |
| `PDL` | `professional_development_leave` | Professional-development leave | May be paid work away or unavailable | Unknown | Requires confirmation |
| `R/L` | `recreational_leave` | Possibly recreational leave | Likely prevents standard assignment | Unknown | Requires confirmation |
| `DCT` | `dct` | Meaning not established | Unknown | Unknown | Requires confirmation |
| `Leave` | `leave` | Generic leave | MVP-authored leave is a hard prohibition; workbook mapping still requires confirmation | Unknown | Requires confirmation |
| `sick` | `sick_leave` | Sickness absence | Likely prevents assignment | Unknown | Requires confirmation |
| `TEACH PM` | `teaching` | Afternoon teaching commitment | May replace or coexist with clinical work | Unknown | Requires confirmation |
| `Exam 1`, `Exam 2`, `Exam 3` | `examination` | Examination commitment/stage | May constrain availability | Unknown | Requires confirmation |
| `CME` | `continuing_medical_education` | Continuing medical education | May be paid work away or unavailable | Unknown | Requires confirmation |
| `M & M` | `morbidity_mortality_meeting` | Meeting/education commitment | Annotation or partial-day constraint | Unknown | Requires confirmation |
| `QARTS` | `qarts` | Meaning not established | Unknown | Unknown | Requires confirmation |
| `AM` | `morning_only` | Morning-only notation | Possible partial-day constraint | Unknown | Requires confirmation |
| `Half PM` | `afternoon_half_day` | Afternoon half-day notation | Possible partial-day duty | Unknown | Requires confirmation |
| `5 Hours` | `five_hour_credit_or_duty` | Five-hour value attached to a date | Could be assignment credit or contracted target | Possibly five hours; not confirmed | Requires confirmation |
| `PN########` / `PR########` | `reference_identifier` | Administrative/reference number | No scheduling meaning assumed | None assumed | Requires confirmation |
| `no calls if possible` | `call_preference` | Preference against call | Soft preference unless confirmed otherwise | None assumed | Requires confirmation |
| Free-form workshop/committee/teaching text | `administrative_commitment` | Dated non-standard commitment | May constrain part of a day | Unknown | Requires confirmation |

## MVP vocabulary boundary

Only these application-authored duty values are active in the foundation:

- `standard_day`
- `leave`
- `unavailable`
- `off`

Legacy codes above are not silently converted into these values. A future mapping must identify the source code, normalized meaning, scheduling effect, credited-hours effect, and confirmed authority.
