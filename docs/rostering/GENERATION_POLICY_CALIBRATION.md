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
