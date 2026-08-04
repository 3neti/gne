# Generation Feasibility Model

Feasibility compares required staffing hours with combined doctor targets before generation. This aggregate arithmetic is policy-independent. Individual interpretation of the resulting excess or deficit is policy-dependent.

`AllocateStructuralVariance` accepts the generation input, aggregate feasibility, and the same `ResolvedRosterPolicy` used by the generator and validator. Equal allocation divides by eligible doctor count only when `equal_per_eligible_doctor` is explicitly selected. Proportional allocation weights stable doctor order by target hours and assigns the deterministic hundredth-hour remainder to the final doctor. `unresolved` returns no allocations and forbids residual-fairness classification.

An aggregate difference can therefore remain known while individual fairness remains unknown. This is reported as a policy calibration requirement, not an assignment error and not a balanced result.
