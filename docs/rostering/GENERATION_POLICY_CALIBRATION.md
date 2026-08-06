# Generation Policy Calibration

Department decisions compile through one path:

```text
repository-authored policy definitions
+ audited department confirmation revisions
-> ResolvedRosterPolicy
-> generator + validator + feasibility/quality + explanations
```

The repository defines policy identity, allowed business meaning, question, provisional default, and provenance. Host records retain explicit meeting confirmation and notes as immutable revisions; they do not invent new policy keys. Unknown keys and unsupported closed enum values fail loudly.

Mandatory unresolved questions (unspecified availability and employment-type eligibility) block preview. A provisional policy with an explicit safe fallback remains usable but visibly provisional. Quality questions may permit a technical preview while returning `policy_calibration_required` rather than a fairness claim.

Structural allocation supports only equal per eligible doctor, proportional to target hours, and unresolved. Allocation rounds to hundredths in stable doctor order, with the deterministic remainder assigned to the final doctor so allocations reconcile exactly. No FTE or employment weighting is inferred.

The administrator surface is `/rostering/policy-calibration`. Abilities remain explicit: view, edit, and confirm. Confirmation emits `roster_policy.confirmed`; the generator cannot confirm policy. Generation-run rows retain the fingerprint used at creation.

Confirmation and operational activation are distinct. The surface reports complete-set compatibility, runtime-consumer coverage, period feasibility, department identity, enforcement identity, and explicit fallback use. Required Hours Meaning no longer defines maximum enforcement; Target Hours Enforcement owns that concern.

Every resolution uses `RosterPolicyEvaluationContext`. Effective choice is the highest supported confirmed revision whose inclusive window contains the evaluation date. Rejected revisions never apply; future and expired revisions remain visible; superseded revisions resolve only inside their closed historical window. The fingerprint contains only effective choices. A generation run stores the resolved snapshot and evaluation date.

The confirmation UI renders repository-authored option cards rather than free text. Preview calculates candidate impacts and a deterministic candidate fingerprint without mutation. See [effective-date model](POLICY_EFFECTIVE_DATE_MODEL.md) and [decision workflow](POLICY_DECISION_WORKFLOW.md).

The option cards also render the registered typed controls and confirmability state. Weekend and consecutive limits, employment categories, required-hours effects, and target tolerances must be complete before confirmation. Unsupported authorized-excess and overtime options are visible but disabled. See [parameterization](POLICY_PARAMETERIZATION.md) and [confirmability](POLICY_CONFIRMABILITY.md).
