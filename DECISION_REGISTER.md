# Decision Register

Each accepted decision is durable until superseded by another recorded decision.

## ADR-052 — Deterministic explainable greedy generation

Accepted. Mandatory eligibility precedes documented lexicographic ranking; mathematical optimization is not claimed and the generator remains replaceable.

## ADR-053 — One generation command, one batch revision

Accepted. One run creates many assignments, one revision, one change per assignment, and one generation audit. Manual corrections create later revisions.

## ADR-054 — Generated and manual rosters share semantics

Accepted. Both use the existing roster models, validation, projections, editing, revision, and audit boundaries.

## ADR-055 — Direct-input generation fingerprints

Accepted. The period requirement set isolates candidates. Unrelated doctors, users, periods, timestamps, database IDs, and filenames do not affect identity.

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
**Status:** Superseded in part by ADR-023 · **Date:** 2026-07-22
**Context:** Their contracts were external and not installed. **Decision:** Integrate later through adapters only. **Rationale:** Preserve independent use and non-settlement workflows. **Consequences:** x-document is now installed through the ADR-023 anti-corruption seam; x-change remains optional and unavailable. **Rejected:** Bootstrap hard dependencies.

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

## ADR-020 — Evidence-integrity failures are not readiness states

**Status:** Accepted — 2026-07-23. **Context:** Ambiguous artifact selection and cross-subject references make evidence untrustworthy; presenting them as `unavailable` would make compiler uncertainty resemble an ordinary business condition. **Decision:** `BuildResolvedDocumentSet` classifies only expected direct evidence absence as `pending`. Ambiguity and contamination propagate as domain failures unless repository validation blocks them first. `Unavailable` and `not_applicable` remain reserved for future explicit valid declarations. **Rationale:** Normal inventory must contain only trustworthy evidence. **Consequences:** Integrity defects fail loudly and cannot be consumed by operators or GeNEi as readiness. **Rejected:** Catch-all unavailable entries, filesystem-order selection, and silently softened chain violations.

## ADR-021 — x-document integration uses a versioned anti-corruption contract

**Status:** Accepted — 2026-07-23. **Context:** GNE's internal `ResolvedDocument` may evolve and x-document must remain independently usable. **Decision:** Map one fully resolved document through a single adapter into contract `1.0` DTOs validated by versioned JSON Schemas. The request carries normalized values, allowlisted metadata, optional descriptive evidence, a driver label, and a direct-input fingerprint. **Rationale:** GNE owns meaning while x-document owns expression without namespace or repository coupling. **Consequences:** Pending documents cannot cross the boundary; unsupported values fail; fixtures guard compatibility; GNE owns the preparatory contract until x-document defines its canonical input. **Rejected:** Exposing internal IR directly, repository callbacks, installing x-document now, PDF-specific contracts, and a premature shared package.

## ADR-022 — x-document contract 1.0 schemas are closed and independently enforceable

**Status:** Accepted — 2026-07-23. **Context:** A permissive standalone document schema and duplicated request grammar could not independently protect future consumers. **Decision:** Make the stable-ID `resolved-document.schema.json` authoritative and reference it from the request through an explicit local Opis registry. Enforce recursive discriminator-correct values, closed core objects, trustworthy source references, result/output combinations, and canonical recursively sorted map serialization. **Rationale:** Consumers must trust the published machine contract without booting GNE. **Consequences:** Compatibility fixtures validate against both schemas; malformed nested values and state combinations fail; version `1.0` is closed before external adoption, after which incompatible changes require a new version. **Rejected:** Duplicated grammars, example-only schemas, byte-order-sensitive maps, permissive output objects, and executable filesystem references.

## ADR-023 — GNE invokes x-document only through canonical contract JSON

**Status:** Accepted — 2026-08-01. **Context:** The independent packages were technically complete, but GNE only prepared future transfer DTOs and did not exercise the real representation runtime. **Decision:** Install reviewed x-document and x-document-laravel baselines through symlinked development path repositories. Resolve repository evidence inside GNE, serialize the existing contract `1.0` request, reload and validate that JSON through x-document, and request an allowlisted browser representation through one focused runtime adapter. **Rationale:** This makes the cross-package boundary real without allowing package classes to inspect GNE internals. **Consequences:** Styled composition is available to CLI and future HTTP delivery; pending evidence remains blocked; exact package Git commits are recorded separately from `dev-main`; PHP 8.4 becomes the truthful GNE floor. **Rejected:** Copying package source, directly constructing external DTOs from GNE domain objects, fixture substitution, controller-owned compilation, and moving business resolution into x-document.

## ADR-024 — GNE delegates browser HTTP expression to x-document-laravel

**Status:** Accepted — 2026-08-01. **Context:** Authenticated operators need to view real resolved output without creating a second renderer or weakening the package boundaries. **Decision:** Protect a stable subject/document GET/HEAD route with existing GNE authentication and a narrow demonstration-document gate. The controller selects one allowlisted composition representation, invokes the GNE runtime resolver, and hands the unchanged `BrowserHostResponse` and request context to `DocumentHttpResponseFactory`. **Rationale:** GNE owns business meaning, x-document owns representation, and x-document-laravel owns HTTP. **Consequences:** Exact bytes, media type, filename, length, strong ETag, weak conditional comparison, HEAD, and 304 remain adapter-owned; pending evidence returns 422 and resolved workbench entries link directly to the representation. **Rejected:** Blade wrapping, Inertia rendering, controller-built headers, JSON reserialization, and action execution.

## ADR-025 — Runtime dependency baselines are attested outside requests

**Status:** Accepted — 2026-08-01. **Context:** Composer `dev-main` labels and hardcoded compatibility booleans cannot prove which local source is installed. **Decision:** Resolve each Composer install path and compare its actual Git HEAD with the reviewed commit during diagnostics, tests, and deployment smoke checks. Compile a known invoice as the contract smoke proof. Never execute Git on the browser request path. **Rationale:** Deployment claims require evidence without making user delivery depend on development tooling. **Consequences:** Baseline mismatch fails `gne:mvp:smoke`; diagnostics distinguish installation, commit match, contract smoke, binding, and route availability. **Rejected:** Unconditional compatibility truth, per-request Git calls, and undocumented moving baselines.

## ADR-026 — Storyboards are derived demonstration projections

**Status:** Accepted — 2026-08-02. **Decision:** Keep choreography in `docs/mvp/storyboards`, reconstruct fictional states in isolated repositories, observe compiler results through authenticated capture pages, and write disposable output under `.gne/storyboards`. **Rationale:** Demonstrations remain faithful and subordinate to repository evidence. **Rejected:** Storyboards as lifecycle authority or generated binaries under `business/`.

## ADR-027 — The temporary GNE storyboard preserves an extraction seam

**Status:** Accepted — 2026-08-02. **Decision:** Separate portable definition/frame/build boundaries from the Property Reservation state adapter and Laravel observation route. **Rationale:** A future `3neti/x-storyboard` may extract orchestration without absorbing GNE business compilation. **Rejected:** Immediate package creation or storyboard behavior inside x-document.

## ADR-028 — Static storyboard HTML is the canonical presentation source for final renditions

**Status:** Accepted — 2026-08-02. **Context:** A preliminary metadata-only PDF and a later browser-produced PDF created ambiguous artifact selection, while the HTML print source was not a durable rendition. **Decision:** Final capture must first finalize every frame with screenshot checksum and byte length, then generate a versioned offline HTML site and asset manifest, print the PDF from that finalized HTML, and build the movie manifest from the same ordered frame inventory. Draft mode produces no final HTML or PDF. **Rationale:** One inspectable source prevents rendition drift and makes screenshot claims verifiable. **Consequences:** Final renderers reject planned, missing, and checksum-mismatched frames; Chromium PDF metadata may vary while the semantic print-source fingerprint remains stable. **Rejected:** Metadata placeholders in final artifacts, pre-capture PDFs, hidden temporary HTML, and independently ordered rendition pipelines.

## ADR-029 — Lifecycle storyboards capture one real authenticated journey

**Status:** Accepted — 2026-08-02. **Decision:** Capture the public login and all subsequent protected production surfaces through one continuous interactively authenticated browser context. Each frame declares an actual capture route and type; non-product explanations are visibly labeled. Local authenticated capture requests may select validated disposable staged repository roots, while normal requests retain canonical repository state. **Rationale:** A screenshot proves application behavior only when the real route, session, response, and visible marker are verified. **Rejected:** independent session injection per frame and screenshots of explanation wrappers claimed as product pages.

## ADR-030 — Subject authorization is deliberate host-owned operational policy

**Status:** Accepted — 2026-08-03. **Decision:** Store exact user/Compilation Subject/permission grants in a rebuild-independent host table; retain subject identity in canonical repository source and restrict global workbenches to deliberately designated operators. **Rationale:** Authentication alone proves identity, not authority over every business case. **Consequences:** The MVP has one `view` permission, explicit grant/revoke/list commands, immediate expiry and revocation, no wildcard, and no claim of enterprise tenancy. **Rejected:** granting every authenticated user access, filename inference, repository-authored user ACLs, and operator bypass of subject grants.

## ADR-031 — Subject authorization precedes resolution and document expression

**Status:** Accepted — 2026-08-03. **Decision:** Resolve a route subject identity, authorize it, and only then invoke document resolution or x-document; filter unauthorized subjects before inventory serialization. **Rationale:** A denial must reveal neither readiness nor document content and must consume no compiler or document-driver work. **Consequences:** Unknown subject is `404`, known ungranted subject is `403`, and denied delivery cannot invoke x-document. **Rejected:** post-resolution checks, client-side filtering, and broad demonstration gates.

## ADR-032 — The legacy anaesthesia roster is discovery evidence, not the runtime domain model

**Status:** Accepted — 2026-08-03. **Context:** The supplied four-week workbook combines doctor identity, employment notation, multi-row duty segments, leave, call, overtime, notes, colours, and unlabelled staffing numbers in one visual surface with no formulas. **Decision:** Normalize those concepts into doctors, roster periods/days, explicit requirements, and one counted daily assignment for the first release. Preserve nullable duty code, start/end time, and credited-hours seams without making generation segment-driven. Defer production import and every unconfirmed legacy-code mapping. **Rationale:** The workbook reveals future compatibility needs but is not a safe executable grammar. **Consequences:** Discovery documents classify observed, inferred, and unresolved meanings; clean demo data uses confirmed semantics only; no generic workforce-optimisation abstraction is introduced. **Rejected:** copying workbook rows into tables, treating colours or codes as canonical policy, automatic import, and implementing multi-segment/on-call/payroll rules during foundation.

## ADR-033 — One counted primary daily assignment defines the rostering MVP identity

**Status:** Accepted — 2026-08-03. **Decision:** Enforce at most one assignment for `(doctor, roster period, roster day)` and retain optional duty code, time, credited-hours, and notes fields. **Rationale:** It provides a truthful counted-day foundation without pretending the workbook's multiple rows are understood. **Consequences:** Split duties and annotations require a later identity design. **Rejected:** spreadsheet-row identity and multiple same-day duties now.

## ADR-034 — Roster-period required hours are explicit

**Status:** Accepted — 2026-08-03. **Decision:** Store one explicit target per doctor and roster period with an explicit source. **Rationale:** Workbook contract labels do not establish their period or conversion. **Consequences:** No FTE or contracted-hours derivation exists. **Rejected:** automatic 80/40-hour interpretation.

## ADR-035 — Daily staffing requirements are explicitly authored

**Status:** Accepted — 2026-08-03. **Decision:** Persist the current non-negative requirement on every roster day; specific-date input overrides period weekday/weekend defaults. **Rationale:** The workbook's bottom-row counts are unconfirmed. **Consequences:** Requirements are auditable headcount inputs, not inferred demand. **Rejected:** workbook import, doctor-count inference, and dynamic colour logic.

## ADR-036 — Roster generation remains behind a future boundary

**Status:** Accepted — 2026-08-03. **Decision:** Do not implement a generator or interface until requests, availability, and resolved policy semantics are stable. The intended future boundary accepts a roster period and resolved roster policy and returns a generated result. **Rationale:** Premature optimization would encode guesses as policy. **Consequences:** Foundation UI cannot generate or simulate a roster. **Rejected:** OR-Tools, generic optimization packages, or spreadsheet heuristics in this slice.

## ADR-037 — Foundation readiness is blocked by errors, not warnings

**Status:** Accepted — 2026-08-03. **Decision:** Preserve all deterministic findings in the transition result while blocking `ready_for_generation` only when an error exists. **Rationale:** Missing explicit targets and zero staffing are visible, remediable warnings in the foundation grammar. **Rejected:** treating every finding as a lifecycle veto or hiding warnings after transition.

## ADR-038 — Assignment mutation and audit form one transaction

**Status:** Accepted — 2026-08-03. **Decision:** Create an assignment and its safe `roster_assignment.created` audit evidence atomically. Translate only the known primary-assignment uniqueness race into a domain exception. **Rationale:** Accepted mutations require immediate evidence; rejected attempts are not mutation events. **Rejected:** best-effort audit, raw uniqueness exceptions as domain behavior, and broad database-exception normalization.

## ADR-039 — Lifecycle scenarios are allowlisted, isolated proofs

**Status:** Accepted — 2026-08-03. **Decision:** Repository YAML may select only a closed operation vocabulary executed through application services, with rollback by default. **Rationale:** Durable examples should prove lifecycle behavior without becoming arbitrary code execution or a workflow engine. **Rejected:** PHP class names in YAML, service-container lookup, SQL/shell steps, direct status mutation, and implicit persistent demo state.

## ADR-040 — Doctor requests are host-owned inputs compiled into availability

**Status:** Accepted — 2026-08-03. **Decision:** Persist normalized mutable request/date records in the host and derive effective availability from accepted requests. **Rationale:** Requests are operational planning state, not canonical repository evidence or generated assignments. **Rejected:** spreadsheet cells, repository artifacts, and persisted calculated calendars as truth.

## ADR-041 — Hard request conflicts block readiness; soft conflicts warn

**Status:** Accepted — 2026-08-03. **Decision:** Leave/unavailability precedence determines effective state without hiding conflicts. Available/unavailable and leave/available are errors; preference conflicts are warnings. **Rationale:** Readiness requires trustworthy prohibitions while preserving human preference nuance. **Rejected:** silent precedence and treating every preference conflict as a veto.

## ADR-042 — Finalized scenario data drives static HTML and PDF evidence

**Status:** Accepted — 2026-08-03. **Decision:** Render disposable HTML from the finalized scenario projection, then print it through existing Playwright/Chromium infrastructure. **Rationale:** Human review needs portable evidence without expanding x-document or creating a PDF subsystem. **Rejected:** direct drawing commands, PDF as canonical truth, and polished success artifacts for failed scenarios.

## ADR-043 — Unspecified active doctors are provisionally eligible

**Status:** Accepted provisionally — 2026-08-03. **Decision:** Treat explicit availability as positive evidence, not permission, and count an active doctor without an effective accepted request as `unspecified` and eligible for the MVP. **Rationale:** The current request grammar captures exceptions and preferences but no confirmed opt-in-only rule. **Consequences:** Reports distinguish explicit from unspecified eligibility and clearly require department confirmation before production. **Rejected:** treating silence as explicit availability, treating explicit availability as assignment authorization, and inventing an opt-in-only policy.

## ADR-044 — Daily eligible-pool sufficiency gates readiness

**Status:** Accepted — 2026-08-03. **Decision:** Require `explicit available + unspecified >= required doctor count` for every roster day before `ready_for_generation`. Use one mutually exclusive effective state per active doctor/date; retain preferences and conflicts as overlays. **Rationale:** Generation cannot begin with an obviously insufficient eligible pool, while readiness must not claim feasibility or balance. **Consequences:** Shortages are deterministic error findings; calendar counts reconcile to the active population. **Rejected:** UI-owned sufficiency, double-counted states, and readiness as a roster-validity guarantee.

## ADR-045 — Availability artifacts render a visual calendar without implying assignments

**Status:** Accepted — 2026-08-03. **Decision:** Project the resolved 28-day availability input as four weekly date-card bands plus a doctor-by-date matrix in UI, self-contained HTML, and Chromium PDF. Label all counts and states as availability inputs and prominently state that no assignments were generated. **Rationale:** Department review needs visually appreciable staffing evidence before manual or automatic roster construction exists. **Consequences:** The same server-derived semantics feed every presentation; colours are supplemented by text; artifacts remain disposable. **Rejected:** table-only reporting, UI-side eligibility calculation, and calendar marks that resemble assignments.

## ADR-046 — Manual roster mutations are previewed, validated, revised, and audited atomically

**Status:** Accepted — 2026-08-03. **Decision:** Simulate previews in rolled-back transactions and commit each authorised add, remove, move, or replace together with post-mutation validation, one revision, one change record, and assignment/revision audit evidence. **Rationale:** Human roster decisions must be explainable without partial history or preview side effects. **Consequences:** Mandatory eligibility failures reject before mutation; removals may leave an invalid draft visible; unexpected audit failures roll the action back. **Rejected:** controller-owned rules, best-effort audit, mutable history, and success audit for rejected attempts.

## ADR-047 — Every committed manual roster change creates one immutable revision

**Status:** Accepted — 2026-08-03. **Decision:** Number revisions monotonically per period and prohibit application updates or deletion of revision snapshots and change records. **Rationale:** Review requires a stable answer to who changed what and what validation followed. **Consequences:** Revisions are operational evidence, not event sourcing. **Rejected:** overwriting the latest snapshot and one revision per database row in a multi-row action.

## ADR-048 — Assigned-roster artifacts are distinct from availability evidence

**Status:** Accepted — 2026-08-03. **Decision:** Render the manual scenario from finalized assignment projections and label it “Manually Authored Draft — Not Yet Published.” **Rationale:** Eligibility is an input while assignments are deliberate decisions. **Consequences:** The report includes assigned names, staffing, hours, validation, revisions, and audit without claiming automatic generation or publication. **Rejected:** reusing availability cards as an assigned roster and treating PDF as canonical.

## ADR-049 — Rostering audit evidence is scoped by exact roster period

**Status:** Accepted — 2026-08-03. **Decision:** Record nullable `roster_period_id` on every period-owned rostering audit inside the mutation transaction and query period history only through `ListRosterPeriodAuditEntries` using that foreign key. Doctor administration may remain unscoped. Deterministic backfill uses direct model identity or explicit structured `roster_period_id`; unresolved rows remain unscoped and cannot enter a period report. **Rationale:** Audit evidence must never leak between business periods, and scope cannot be reconstructed later from action names, prefixes, or free text. **Consequences:** UI, lifecycle reports, JSON, HTML, and PDF share one exact-period boundary; period deletion nulls the foreign key while immutable textual identity remains. **Rejected:** global action filtering, payload searches at report time, and identifier-prefix matching.

## ADR-050 — Operator artifacts summarize history while machine evidence remains complete

**Status:** Accepted — 2026-08-03. **Decision:** Show current totals, mutation/action counts, notable operations, and recent entries in the primary UI/PDF; retain all revisions and exact-period audits in report JSON and dedicated HTML/history routes. Label revision validation as roster state after revision. **Rationale:** Administrators need a reviewable artifact without losing durable proof. **Rejected:** raw hundreds-row PDF dumps and truncating machine evidence.

## ADR-051 — Draft generation owns one top-level roster revision

**Status:** Accepted — 2026-08-03. **Decision:** A future automatic draft-generation command creates one top-level roster revision containing many revision changes, one generation audit event, and one post-generation validation snapshot. Manual add/remove/move/replace commands continue to create one revision per administrator action. **Rationale:** A generated draft is one business command, not hundreds of independent administrator actions. **Consequences:** The future generator must own revision scope and may reuse lower-level persistence only without creating per-assignment top-level revisions. **Rejected:** calling the manual command once per generated assignment and producing hundreds of top-level revisions. No generator is implemented by this ADR.
