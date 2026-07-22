# Decision Register

Each accepted decision is durable until superseded by another recorded decision.

## ADR-001 — Repository is canonical; database is projection
**Status:** Accepted · **Date:** 2026-07-22
**Context:** Operational schemas obscure authored business meaning. **Decision:** Repository files are canonical and database rows rebuildable. **Rationale:** Human/AI readability and deterministic recovery. **Consequences:** Materializers retain stable identifiers and support replacement. **Rejected:** Database-first domain truth.

## ADR-002 — Standalone Laravel application
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** GNE needs a control plane and compiler runtime. **Decision:** GNE is an application, not a reusable domain package. **Rationale:** Cohesive operations without constraining external packages. **Consequences:** Reusable seams remain explicit. **Rejected:** Monolithic reusable package.

## ADR-003 — Accepted artifacts are immutable
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Silent edits destroy evidence. **Decision:** Corrections add revisions and supersession links. **Rationale:** Auditability. **Consequences:** Prior revisions persist. **Rejected:** In-place updates.

## ADR-004 — Identity is repository-native
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Database IDs disappear on rebuild. **Decision:** Stable business identifiers originate in source. **Rationale:** Durable references. **Consequences:** Numeric IDs are projection details. **Rejected:** Primary-key identity.

## ADR-005 — Generated projections are disposable
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Indexes and caches are derived. **Decision:** Generated state lives under `.gne/` or projection tables and is rebuildable. **Rationale:** Prevent authority drift. **Consequences:** Evidence paths are required. **Rejected:** Hand-maintained indexes.

## ADR-006 — GeNEi is provider-independent
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Reasoning engines change. **Decision:** GeNEi is a repository role and perspectives, not a model. **Rationale:** Portability. **Consequences:** Future adapters preserve traceability. **Rejected:** Provider-specific core.

## ADR-007 — Browser and document outputs are peers
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Neither UI nor PDF is business truth. **Decision:** Both consume a resolved document projection. **Rationale:** Driver neutrality. **Consequences:** No Adobe logic in core. **Rejected:** Browser-as-template authority.

## ADR-008 — x-document and x-change stay optional
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Their contracts are external and not installed. **Decision:** Integrate later through adapters only. **Rationale:** Preserve independent use and non-settlement workflows. **Consequences:** Drivers report unavailable honestly. **Rejected:** Bootstrap hard dependencies.

## ADR-009 — Native Laravel authentication protects control plane
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Administration and approval need access control. **Decision:** Retain starter-kit authentication. **Rationale:** Established secure boundary. **Consequences:** Workbench routes require auth and verification. **Rejected:** Custom authentication.

## ADR-010 — Public ceremonies need not require accounts
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Customers may interact once. **Decision:** Future ceremonies may use signed links, OTP, or transaction credentials. **Rationale:** Fit identity to ceremony. **Consequences:** No customer-account assumption. **Rejected:** Mandatory accounts.

## ADR-011 — Laravel teams are not organizations
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Generic teams would pre-empt business semantics. **Decision:** Do not enable team support. **Rationale:** Organization must be explicit. **Consequences:** No tenancy scaffold now. **Rejected:** Starter-kit teams.

## ADR-012 — Membership modeling is deferred
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Organization, repository, membership, role, and authority need deliberate semantics. **Decision:** Defer them. **Rationale:** Avoid premature tenancy. **Consequences:** Bootstrap is single-repository. **Rejected:** Generic multi-tenancy.

## ADR-013 — Validate one vertical scenario first
**Status:** Accepted · **Date:** 2026-07-22  
**Context:** Broad generalization can hide weak semantics. **Decision:** Prove manual-payment property reservation first. **Rationale:** Concrete evidence tests boundaries. **Consequences:** General engines remain deferred. **Rejected:** Universal ERP/workflow schema.

## ADR-014 — Repository evidence is portable and byte-fingerprinted
**Status:** Accepted · **Date:** 2026-07-22
**Context:** Laravel-root-relative paths and metadata-only hashes could hide canonical changes. **Decision:** Resolve all evidence relative to the supplied repository root and fingerprint ordered canonical relative paths plus raw bytes. Profiles declare their own supporting files. **Rationale:** Portable discovery, reliable drift detection, and profile neutrality. **Consequences:** Any canonical byte change produces a new fingerprint, while inventory remains a separate concern. **Rejected:** `base_path()` addressing, example-specific validation, and inventory-derived fingerprints.

## ADR-015 — ResolvedDocument is the document compiler IR
**Status:** Accepted · **Date:** 2026-07-22
**Context:** Business artifacts are facts, while browser and PDF outputs are projections. **Decision:** Resolve repository-authored document definitions and accepted artifact revisions into a deterministic, evidence-bearing `ResolvedDocument` before invoking any driver. **Rationale:** One explainable business representation can support peer outputs without making presentation canonical. **Consequences:** Every resolved field cites artifact evidence; browser code contains no field-resolution logic; PDF and x-document remain deferred. **Rejected:** Vue-authored business documents, HTML as canonical form, and direct artifact-to-PDF compilation.

## ADR-016 — Resolved-document identity covers the complete evidence set
**Status:** Accepted · **Date:** 2026-07-22
**Context:** A primary-artifact revision cannot identify content that also depends on secondary artifacts, while a repository fingerprint changes for unrelated evidence. **Decision:** Derive resolved-document identity from a resolution fingerprint covering the definition identifier, definition revision and source bytes, plus every deterministically ordered selected artifact identity, revision, type, path, and source bytes. Retain the primary artifact separately as the business anchor. **Rationale:** Prevent collisions, stale projections, and ambiguous provenance. **Consequences:** Any direct input change creates a new immutable identity; unrelated repository changes do not. **Rejected:** Primary-revision identifiers, sequential revision claims without a ledger, and repository-wide fingerprints as document identity.

## ADR-017 — Resolved documents require an explicit Compilation Subject
**Status:** Accepted · **Date:** 2026-07-22
**Context:** Profile and scenario can contain multiple unrelated transactions. **Decision:** Every resolution request names a repository-authored Compilation Subject; a deterministic selector supplies only accepted revisions in that subject, and cross-subject references or ambiguous candidates fail clearly. The subject identity is a direct resolution-fingerprint input. **Rationale:** Prevent invalid composite documents and preserve transaction-level provenance. **Consequences:** Artifact source and projections expose subject identifier and type; CLI and browser URLs use stable subject identifiers. **Rejected:** Independently selecting the latest artifact by type across a profile/scenario, filename inference, and database IDs.

## ADR-018 — Authored payloads and document definitions validate before compilation
**Status:** Accepted · **Date:** 2026-07-22
**Context:** Resolution must not discover preventable authoring defects piecemeal. **Decision:** Profiles explicitly map artifact types to profile-owned JSON Schema 2020-12 files; accepted payloads validate with Opis JSON Schema 2.x. GNE owns a strict document-definition schema and contextual validator. Core grammar rejects unknown keys; `metadata` and `extensions` are reserved objects. **Rationale:** Make repository authority deterministic and explainable. **Consequences:** Errors block compile, warnings describe valid resolution absence, and findings cite repository evidence. **Rejected:** Filename-derived mappings, home-grown schema validation, silent normalization, and resolver-time authoring checks.

## ADR-019 — Each subject exposes a derived document set and lifecycle inventory

**Status:** Accepted — 2026-07-23. **Context:** Resolving one requested document does not explain the full document state of a business case. **Decision:** Derive one immutable `ResolvedDocumentSet` per Compilation Subject by applying valid profile/scenario definitions to its selected chain through `ResolveDocument`; derive lifecycle position from declared lifecycle evidence. **Rationale:** Operators, GeNEi, APIs, and peer drivers need one deterministic explanation of resolved and pending documents without executing business actions. **Consequences:** Pending entries carry structured missing evidence; lifecycle gaps remain visible; browser and JSON drivers only project prepared inventory; set fingerprints use direct subject evidence and definition inputs, not the repository fingerprint. **Rejected:** Driver-owned readiness, mutable workflow status rows, repository-wide set identity, and treating missing evidence as invalid source.
