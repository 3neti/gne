# Resolved Policy Coherence

GNE validates the complete resolved anaesthesia roster policy set before treating it as operational. Individual confirmability proves only that one authored choice and its parameters are well formed.

`ValidateResolvedRosterPolicyCoherence` is the single owner of cross-policy classification. It evaluates required-hours compatibility, availability/employment scope, complete configuration, declared support, runtime-consumer coverage, and optional selected-period feasibility. Structured findings carry policy keys, selected values, period, affected dates and doctors, and a required correction.

Required Hours Meaning owns what the roster-period number represents. Target Hours Enforcement owns how that number is applied. Their compatibility is closed and fail-safe; unknown combinations are rejected.

Confirmation and activation are separate. A department-confirmed set may be visible as `confirmed_but_not_enforceable`. Generation then uses the explicitly identified repository provisional fallback rather than silently applying the blocked set. Historical generation snapshots remain immutable.

Public-holiday target treatment is discovery metadata. It has no runtime consumer, is not required for current confirmation, and is excluded from the enforcement fingerprint.
