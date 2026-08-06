# ADR: Typed roster policy confirmability and permanent supersession

**Status:** Accepted — 2026-08-06.

## Decision

Repository definitions are the sole source of typed parameter schemas. A choice is confirmable only when supported and operationally complete. Unsupported choices remain visible but fail closed. Effective-policy and historical generation fingerprints include normalized configuration. New confirmed revisions permanently supersede prior revisions; expired successors do not reactivate predecessors. Public-holiday and variable credited-hours topics remain discovery-only.

## Consequences

The UI and decision artifact render registered fields rather than inventing controls. Confirmation, generation, validation, quality analysis, and historical replay share one resolved configuration. Temporary restoration, on-call, overtime, holiday sourcing, variable duty credits, optimization, and publication remain deferred.

