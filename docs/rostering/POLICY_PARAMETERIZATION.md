# Roster Policy Parameterization

Repository-authored policy options own their typed configuration grammar. Each option declares `supported`, `parameters`, fixed configuration, operational impacts, and unsupported dependencies. Parameter types in this release are integer, boolean, enum, and non-empty enum list. Unknown keys, wrong types, out-of-range integers, empty required lists, and attempts to change fixed configuration fail closed.

Weekend thresholds require a non-negative maximum difference and combined or separate Saturday/Sunday grouping. Consecutive-day thresholds require a positive maximum; leave and unassigned dates break runs. Employment-specific availability requires a non-empty subset of full-time, part-time, visiting, and locum categories. Required-hours choices use the fixed roster-period basis and deliberate leave, education, and public-holiday booleans. Hard target limits require non-negative tolerances. Structural allocation fixes hundredth-hour rounding and largest-fractional-remainder distribution with stable doctor identity as the tie-break.

Public holidays and variable credited hours are discovery-only. On-call, overtime, override workflow, public-holiday sourcing, and variable duty credits are unsupported in this release.
# Coherence constraint

Typed configuration is necessary but not sufficient. A configured choice becomes operational only after complete-set compatibility, consumer coverage, and selected-period feasibility pass. Holiday discovery metadata is outside the current enforcement fingerprint.

