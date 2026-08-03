# GNE Grammar

A **Roster Generator** computes a proposal from normalized facts. **Resolved Roster Policy** is stable runtime policy meaning. A **Generation Run** records one committed proposal. A **Generation Fingerprint** identifies direct policy/input/result semantics. A **Generated Assignment Explanation** records deterministic reason codes and ranking facts.

A **Storyboard Definition** is canonical demonstration choreography, not business source. A **Frame** is an ordered observation with act, narrative persona, display route, stage, action, expected marker, and explicit **Capture**. Capture declares `type`, actual route, authentication requirement, expected final route, and whether the route is a production surface. Types are **application**, **document**, **evidence**, and **explanation**. An explanation is explicitly not a production application screen. A **Snapshot** records repository, subject, lifecycle, document-set, browser, and artifact evidence. A **Storyboard Manifest** binds frames to snapshots and disposable projections.

A **Captured and Verified Frame** has status `captured_and_verified`, the observed final application route and HTTP status, a verified visible marker, a local relative PNG reference, SHA-256, and byte length. `planned`, `failed`, `captured`, and `skipped` are non-final states. An **Authenticated Lifecycle Journey** uses interactive login, one browser context, one ephemeral fictional operator, and preserved session cookies; personas remain narrative viewpoints unless role authorization is implemented. A **Finalized Frame Inventory** is the ordered, fingerprinted source shared by HTML, PDF, and movie renditions. A **Storyboard HTML Rendition** (`gne-storyboard-html/1.0`) composes the actual screenshot with narrative annotation; it neither executes actions nor authors business truth. **Draft Mode** validates and plans outputs without claiming final HTML/PDF. **Final Mode** requires every mandatory capture to be verified before rendering.

| Term | Canonical meaning |
|---|---|
| GNE | Repository-native Business Compiler and runtime control plane. |
| Subject Access Grant | Host-owned operational authorization linking one user to one exact repository-native Compilation Subject and one permission; it is not canonical business evidence and survives repository projection rebuilds. |
| Subject Permission | Explicit host permission over one Compilation Subject. The MVP vocabulary contains only `view`; no wildcard or inferred permission exists. |
| Operator | Deliberately designated host user allowed to open global repository workbenches. Operator status does not replace an exact subject grant. |
| Authorization Decision | Derived allow or deny result with a safe reason such as active, missing, or expired grant; it is evaluated before resolution and external document compilation. |
| GeNEi | Provider-independent repository-native AI role, pronounced “Genie”. |
| Business Repository | Version-controlled canonical business source and evidence. |
| Business Source | Authored repository evidence from which meaning derives. |
| Business Profile | Coherent vocabulary, policies, schemas, lifecycles, scenarios, and projections. |
| Business Artifact | Stable representation of a business occurrence or statement. |
| Accepted Business Fact | Artifact revision accepted under policy and thereafter immutable. |
| Artifact Type | Explicit semantic classification. |
| Artifact Identity | Stable repository-native identifier independent of database IDs. |
| Artifact Revision | Immutable version identity within an artifact identity. |
| Artifact Relationship | Explicit typed link to an identity and optional revision. |
| Superseded Artifact | Preserved revision replaced in effect by an explicit newer revision. |
| Provenance | Evidence of source, actor, time, acceptance, and recording context. |
| Schema | Machine-readable payload constraint. |
| Policy | Human-readable acceptance or interpretation constraint. |
| Workflow | Ordered work coordination, not necessarily a universal engine. |
| Lifecycle | Named states and evidence-backed transitions. |
| Scenario | Bounded path through profile semantics. |
| Projection | Non-canonical representation derived from repository truth. |
| Materialization | Rebuilding operational state from repository evidence. |
| Compilation | Resolving repository meaning into a projection plan or output. |
| Compiler | Services that discover, validate, interpret, and project source. |
| Document Definition | Authored declaration of a document projection. |
| Resolved Document | Deterministic driver-neutral compiler intermediate representation resolved from accepted artifacts, with ordered semantic content and evidence. |
| Compilation Subject | Stable repository-native identifier and business-language type for the bounded case or transaction being compiled; independent of database IDs, profiles, and scenarios. |
| Artifact Membership | Explicit repository-authored association between an artifact and its Compilation Subject. |
| Artifact Chain | Deterministically selected accepted artifact revisions belonging to exactly one Compilation Subject. |
| Chain Selector | Service that selects and checks one coherent Artifact Chain without interpreting document presentation. |
| Compilation Request | Document-definition identifier plus the explicit Compilation Subject to resolve. |
| Document Definition Grammar | GNE-owned, machine-validatable structure supported by the current document resolver. |
| Artifact Schema | Profile-owned JSON Schema that constrains one artifact type's payload. |
| Artifact Type Declaration | Explicit profile mapping from a business artifact type to its canonical schema. |
| Payload Path | Canonical dot path beginning with `payload.` that addresses a property declared by an artifact schema. |
| Schema Validation | Deterministic comparison of accepted artifact payloads with their declared schemas. |
| Definition Validation | Structural and contextual validation of a repository-authored document definition before compilation. |
| Compilation Readiness | State in which authored source is valid; evidence may still be legitimately absent for a subject. |
| Authoring Error | Invalid canonical source that blocks compilation. |
| Resolution Absence | Valid definition that cannot resolve for a subject because accepted evidence is not yet present. |
| Primary Artifact | Selected accepted artifact that anchors a resolved document's status and business purpose; it is only one member of the evidence set and does not solely determine document identity. |
| Resolution Fingerprint | Deterministic hash of the document definition revision/source and complete ordered selected evidence set; the basis of immutable resolved-document identity. |
| Evidence Set | All selected artifact identities, revisions, types, source paths, and source fingerprints that materially contribute to one resolution. |
| Document Definition Revision | Authored revision of a repository document definition, paired with its source fingerprint for resolution identity. |
| Resolved Field | Named value in a resolved document carrying direct artifact, revision, path, and value-path evidence. |
| Document Resolver | Compiler service that interprets an authored document definition and accepted artifacts into a Resolved Document. |
| Document Driver | Adapter projecting a resolved document without introducing business meaning. |
| Browser Projection | Disposable browser-consumable structure produced from a Resolved Document; never canonical. |
| Resolved Document Set | Deterministic, derived inventory of valid document definitions for exactly one Compilation Subject, including readiness and resolved IR where available. |
| Document Inventory Entry | Subject-specific assessment of one valid Document Definition. |
| Document Readiness | Derived ability to resolve a definition from the selected accepted Artifact Chain. |
| Resolved | Readiness state in which all directly required accepted evidence exists and a Resolved Document was produced. |
| Pending | Readiness state in which a valid applicable definition lacks directly required accepted evidence as an ordinary lifecycle condition. |
| Unavailable | Reserved readiness state for a valid applicable document intentionally withheld by a future explicit, trustworthy policy or capability rule; never a label for ambiguous or contaminated evidence. |
| Not Applicable | Reserved readiness state for a valid definition excluded by future explicit applicability declarations; never inferred from missing evidence. |
| Missing Evidence | Structured description of repository evidence directly required by a definition but absent from the selected chain. |
| Evidence-Integrity Failure | Ambiguous, cross-subject, or otherwise untrustworthy evidence that validation or compilation rejects rather than classifying as document readiness. |
| Lifecycle Position | Read-only derivation of contiguous completed, next, future, and gap stages from a declared lifecycle and selected accepted evidence. |
| Semantic Index | Disposable AI-readable metadata linked to source evidence. |
| Repository Agent | Human or software actor operating under repository constraints. |
| Runtime State | Sessions, queues, locks, caches, OTPs, and temporary tokens. |
| Operational Projection | Runtime structure optimized for execution, not authority. |
| Canonical Source | Accepted repository representation used for rebuilds. |
| Rebuild | Recreating derived state without changing canonical source. |
| Explainability | Ability to state how evidence produced a result. |
| Traceability | Ability to follow a result to identities, revisions, relationships, and paths. |
| x-document Adapter | Anti-corruption mapper that translates GNE's internal Resolved Document into the versioned external contract without resolving or rendering business meaning. |
| External Document Contract | Portable, versioned transfer representation consumed independently of GNE classes and repository services. |
| Contract Version | Identifier for the external transfer schema; version `1.0` is distinct from document, definition, driver, and output revisions. |
| XDocument Compilation Request | Versioned external document payload plus requested driver, capabilities, options, correlation identity, and deterministic request fingerprint. |
| XDocument Compilation Result | Future external response describing success, unsupported behavior, or failure and an optional output; never document readiness. |
| Request Fingerprint | Deterministic hash of contract version, external resolved meaning, requested driver, capabilities, and normalized options after recursive map-key canonicalization. |
| Driver Request | Non-authoritative label naming the desired future projection driver without claiming availability. |
| Capability | Named external-contract semantic requested from a future driver, currently limited to actions, attachments, and evidence. |
| Output Reference | Portable output metadata or content reference that never requires an absolute local filesystem path. |
| Anti-Corruption Layer | Boundary that prevents an external system from depending on GNE's internal compiler and repository models. |
| Canonical Contract Schema | Independently enforceable, versioned JSON Schema that authoritatively defines one external contract concept. |
| Canonical Serialization | Transfer encoding that recursively sorts map keys while preserving list order, providing stable cross-language bytes and fingerprints. |
| Source Reference | Opaque, non-executable provenance or content reference; it is not a filesystem instruction and cannot be an absolute local path or `file:` URI. |
| Schema Closure | State in which producer DTOs, fixtures, standalone schemas, references, recursive values, and result invariants enforce the same versioned semantics. |
| x-document Runtime Adapter | GNE integration service that resolves repository meaning, crosses contract `1.0` as canonical JSON, loads it into real x-document DTOs, and requests one allowlisted browser representation. |
| Browser Host Response | Framework-neutral x-document result carrying exact representation bytes, format, media type, checksum, byte length, filename, disposition, and strong ETag. |
| Styled Browser Composition | Read-only `browser-composition-html-styled/1.0` expression combining resolved document content and inert interaction declarations without JavaScript, forms, or action execution. |
| Runtime Package Baseline | Exact reviewed Git commit recorded alongside a Composer development version so local path integration never disguises a moving dependency. |
| Authenticated Browser Representation Route | Stable GNE GET/HEAD seam that authorizes one subject/document and selects one allowlisted composition representation without rendering it. |
| HTTP Response Factory | x-document-laravel contract that expresses an existing Browser Host Response as exact HTTP bytes and metadata. |
| Package Baseline Attestation | Diagnostic comparison of an installed local package's actual Git HEAD with its expected reviewed commit. |
| Contract Smoke Proof | Real known-subject compilation through GNE preparation, x-document validation, and browser expression; stronger than class-existence diagnostics. |
| Manual Roster Assignment | Authorised deliberate placement of one active doctor on one date; availability alone is never an assignment. |
| Roster Mutation Preview | Rolled-back simulation of an add, remove, move, or replace command with resulting validation and impact but no durable evidence. |
| Roster Validation Result | Deterministically ordered `valid`, `valid_with_warnings`, or `invalid` assessment of assignments, staffing, hours, and preferences. |
| Roster Revision | Immutable monotonic per-period snapshot recording one committed manual action, its structured changes, and post-mutation validation. |
| Assigned Hours | Sum of explicit credited hours for one doctor's assignments in one roster period. |
| Staffing Status | Derived comparison of assigned count with required count: `understaffed`, `fully_staffed`, or `overstaffed`. |
| Roster Audit Scope | Explicit nullable `roster_period_id` recorded with a rostering mutation; period-owned reports accept only exact foreign-key matches. |
| Operator Roster History | Concise exact-period summary of revision and audit evidence for routine review. |
| Complete Roster History | Exhaustive exact-period revision and audit evidence retained in JSON and detailed HTML. |
| Roster State After Revision | Validation state of the complete roster immediately after a revision; it does not classify the mutation command itself. |
# Anaesthesia rostering grammar

**Doctor** is a stable host clinician record. **Roster Period** is an inclusive calendar planning window. **Roster Day** is one date in that period with an explicit non-negative staffing requirement. **Doctor Roster Requirement** is one explicit non-negative required-hours target for a doctor and period. **Primary Daily Assignment** is the sole counted assignment allowed for one doctor and roster day in the foundation. **Roster Administrator** is the host-owned capability permitted to mutate these records. **Roster Audit Entry** is append-only structured evidence of a major mutation.

The active duty vocabulary is only `standard_day`, `leave`, and `unavailable`. Absence of assignment means off. Contract notation never derives required hours. The operational lifecycle enables `draft → collecting_requests → ready_for_generation`; later declared states are non-operational. Workbook codes are discovery terms, not grammar.

**Foundation Readiness** permits warnings and blocks errors. **Roster Transition Result** exposes from/to state and deterministic findings. **Foundation Lifecycle Scenario** is repository-authored explanatory proof with a fixed operation vocabulary; it is not executable PHP or arbitrary YAML automation. **Assignment Mutation Audit** is the same-transaction evidence emitted only after an assignment is accepted.

**Doctor Schedule Request** is a mutable host record for explicit dates. **Effective Availability** is the derived accepted-request state for one doctor/date. **Hard Request Conflict** is an error that blocks readiness. **Soft Preference Conflict** is a visible warning. **Availability Artifact** is disposable JSON/HTML/PDF evidence and never an assignment roster.

**Staffing Summary** compares required and assigned headcounts without identifying the roster. **Generated Roster Calendar** groups actual assignment identities and provenance by date. **Doctor Assignment Matrix** uses doctors as rows and roster dates as columns, with `G` generated, `M` manually changed, `L` leave, `U` unavailable, `PW` preferred work, `PO` preferred off, and `!` a finding. **Generation State** is revision 1 immediately after generation. **Current Roster State** is the later projection after manual corrections; the two are never interchangeable.

**Explicit Availability** is accepted positive evidence and is not assignment permission. **Unspecified Availability** means no effective accepted request exists for an active doctor/date; it is provisionally eligible until department policy is confirmed. **Blocking Status** is leave, unavailable, conflicted, or none. **Eligible Doctor Pool** is the exclusive count of effectively available plus unspecified active doctors. **Staffing Input Sufficiency** means that pool meets the authored daily requirement; it does not guarantee a valid or balanced roster. **Doctor Availability Matrix** is a derived doctor-by-date report using `A`, `U`, `L`, `PW`, `PO`, `-`, and `!`; it is not a roster.
