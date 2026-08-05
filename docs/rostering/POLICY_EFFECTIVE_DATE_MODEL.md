# Roster Policy Effective-Date Model

Policy resolution is deterministic repository definitions plus append-only operational decisions plus an explicit evaluation date and purpose.

For each key, the resolver chooses the highest supported confirmed revision whose `effective_from` is on or before the evaluation date and whose optional `effective_until` is on or after it. Rejected records never apply. Future and expired records are reported separately. A superseded record can resolve only for a historical date inside its closed window.

Current diagnostics evaluate today. Generation preview and commit evaluate roster-period start. A generated run persists that date, the complete resolved snapshot, and the effective fingerprint. Validation hydrates that snapshot, preventing later policy decisions from rewriting historical meaning.

The fingerprint is calculated only from effective definition content. It excludes current time, UI state, audit identity, unrelated history, and future revisions.
