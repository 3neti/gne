# GeNEi Repository Cognition

> **GENEI.md is not a prompt. It is the repository’s self-description. It explains how the business thinks, how it is organized, what principles govern it, and what invariants GeNEi must preserve while assisting with its evolution.**

GNE is a repository-native Business Compiler. Its north star is: **The business belongs to the repository. Everything operational is a projection.** The accepted business representation must remain understandable to humans, compilers, runtime systems, and capable AI without first querying an operational database.

## How to read this repository

Begin here for durable cognition, `GRAMMAR.md` for vocabulary, `ARCHITECTURE.md` for boundaries, and `DECISION_REGISTER.md` for constraints. Read `business/` as canonical source and accepted evidence. Treat `.gne/`, database rows, caches, indexes, rendered documents, browser views, and reports as derived. Read `COMPASS.md` and `IMPLEMENTATION_STATUS.md` for volatile direction.

Every important concept has a stable repository identity. Relationships, lifecycle transitions, scenarios, provenance, revisions, and supersession are explicit. Accepted facts are immutable: correction adds an artifact or revision and retains prior evidence. Generated semantic facts require a source path and must never invent unsupported meaning.

Repository evidence is addressed relative to the repository being interpreted, never relative to the host application. Each profile declares its own vocabulary, lifecycles, scenarios, policies, documents, and schemas. Canonical fingerprints represent ordered source paths and raw source bytes; inventory summaries and semantic indexes do not substitute for that evidence.

Business artifacts are facts rather than documents. Repository-authored document definitions compile accepted artifacts into `ResolvedDocument`, the driver-neutral intermediate representation. Every resolved field retains direct artifact and revision evidence. Browser, PDF, API, and other outputs are peer projections and must not add business meaning absent from that IR.

## Business cognition and GeNEi

GNE reasons from profile vocabulary, schemas, policies, lifecycles, scenarios, document definitions, decisions, and immutable artifacts. Discovery establishes what exists; validation whether it is acceptable; interpretation resolves expressed meaning; compilation prepares projections; materialization produces disposable operational state. Git supplies provenance, replication, history, and review, but does not define semantics.

**Architect** proposes profiles, schemas, workflows, policies, scenarios, documents, vocabulary, and decisions. **Operator** assists with review, approvals, safe transitions, artifact generation, and repository-safe commands. **Analyst** answers performance, timing, exception, causal, executive, and diagnostic questions from traceable evidence. These are perspectives, not separate models.

GeNEi is a provider-independent repository role, not a foundation model. Future engines must preserve evidence links, distinguish fact from inference, expose uncertainty, and never silently mutate accepted artifacts. Autonomous execution, provider adapters, and LLM tool calling are deferred.

Do not make Eloquent or database rows canonical; edit accepted artifacts in place; infer relationships only from names; place generated output in `business/`; hide vocabulary solely in PHP; build generic CRUD, ERP, workflow, policy, tenancy, vector, settlement, or PDF engines in core; couple GNE to an AI provider; or present browser/PDF as canonical.
