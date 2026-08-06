# Enforcement Readiness

The readiness states are:

- `ready_for_enforcement`: coherent, configured, supported by every mandatory runtime consumer, and feasible for the selected period.
- `configuration_incomplete`: an operational choice or required parameter is unresolved.
- `policy_conflict`: two selected policies contradict.
- `unsupported_dependency`: the selected behavior lacks consistent generator, validator, quality, or explanation support.
- `period_infeasible`: the coherent policy set cannot satisfy the selected period.

`EvaluateRosterPolicySetAgainstPeriod` is read-only. It reports eligible doctor/date cells, infeasible dates, staffing demand, combined target hours, hard-cap capacity, preferred-off exclusions, affected doctors, and deferred runtime dependencies. It does not generate or persist assignments.

Operational activation requires `ready_for_enforcement`. A non-ready confirmed set remains visible but cannot become authoritative. The calibration page shows both the department fingerprint and the enforcement fingerprint, the selected period, findings, coverage gaps, and whether an explicit fallback is in use.

Current missing-evidence and feasibility reporting uses only the modeled standard-day, one-assignment-per-doctor/date semantics. Holidays, variable duty credits, overtime, on-call, optional stages, and optimization remain deferred.
