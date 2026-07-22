# GNE Grammar

| Term | Canonical meaning |
|---|---|
| GNE | Repository-native Business Compiler and runtime control plane. |
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
