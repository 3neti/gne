# Generation Policy Calibration Register

No current implementation assumption is a confirmed department policy unless its status and provenance say so. The repository owns the policy questions and provisional defaults; an administrator confirmation creates an audited, immutable operational revision compiled by `ResolveRosterPolicy`.

| Policy | Department question | Current choice | Status | Impact | Authority / evidence | Open issue |
|---|---|---|---|---|---|---|
| `unspecified_availability` | May a doctor with no accepted request be assigned? | eligible unless blocked | provisional | mandatory | Anaesthesia department / demonstration assumption | Confirm explicit availability rules by employment type. |
| `required_hours_meaning` | What period and obligation does required hours represent? | roster-period target | provisional | quality | Anaesthesia department | Confirm unit, minimum/target/maximum, leave, holidays, education, on-call and overtime. |
| `employment_type_eligibility` | How do full-time, part-time, visiting and locum categories affect eligibility? | all active types eligible | provisional | mandatory | Anaesthesia department | Confirm exclusions, target obligations and excess authorization. |
| `structural_hours_allocation` | How are unavoidable extra or short hours shared? | equal per eligible doctor | provisional | quality | Anaesthesia department / homogeneous demo | Choose equal, proportional to target, or unresolved. |
| `target_hours_cap` | Is target a soft target, minimum or hard maximum? | soft target | provisional | quality | Anaesthesia department | Confirm warnings, errors and authorized excess. |
| `weekend_distribution` | How should weekend and holiday work be shared? | informational | provisional | quality | Anaesthesia department | Define weekend, thresholds and enforcement. |
| `consecutive_day_limit` | What consecutive-day and rest rule applies? | informational | provisional | quality | Anaesthesia department | Define limit, rest, severity and override. |
| `preference_strength` | How strongly do preferred work/off requests influence assignment? | soft preference | provisional | quality | Anaesthesia department | Confirm hard prohibitions and escalation. |

Statuses are `unconfirmed`, `provisional`, `confirmed`, `superseded`, and `rejected`. Effective dates and meeting references are recorded at confirmation. A new confirmation changes the resolved fingerprint; stored generation runs retain their original fingerprint.
