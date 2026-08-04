# Draft Generation Quality

Draft-generation quality is assessed against feasibility rather than raw target variance alone. `AnalyzeRosterGenerationFeasibility` consumes normalized days, daily staffing requirements, active doctors, standard credited hours, and authored doctor targets. It does not inspect generated assignments. Uniform daily hours produce an exact aggregate comparison; mixed hours are reported as indeterminate until candidate-specific duty modeling exists.

For the canonical September scenario, 180 required slots at eight hours require 1,440 staffing hours. Ten 136-hour targets total 1,360 hours. A fully staffed roster therefore requires 80 hours above target. The generated revision gives every doctor 144 hours: raw variance is +8, allocated structural variance is +8, and residual variance is zero. This is `balanced_within_feasibility`, despite the aggregate warning and one preferred-off violation.

The quality projection reports assigned-hour and assignment-count ranges, weekend distribution, preference fulfillment, explicit and unspecified availability use, and consecutive-day runs. Residual variance greater than one standard shift is the initial conservative imbalance-warning threshold. Weekend and consecutive-day metrics are descriptive; no fatigue, rest, or weekend-allocation policy is enforced.

The generator records the top three eligible candidates at each selection with the exact lexicographic facts it used: preferred work, explicit availability, remaining target hours, assignment ratio/count, preferred off, and stable identifier. Operator HTML translates reason codes into readable text. JSON retains codes, names, stable identifiers, and next-best facts.

Quality belongs to generated revision 1. A later manual move is validated and presented as current roster state, but it does not retroactively change the historical generation score. The algorithm remains deterministic greedy, initial-only, standard-day, and non-optimal.
