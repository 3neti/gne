# Implementation Status

| Intended capability | Current implementation | Test coverage | Limitations | Status | Next action |
|---|---|---|---|---|---|
| Canonical cognition | Root documentation and business source | Architecture checks | Bootstrap vocabulary only | Implemented | Preserve durability |
| Configuration | Version-1 YAML parser with safe relative paths | Unit/command | No extensible config language | Implemented | Evolve only from need |
| Discovery/validation | Repository-portable inventory plus layered schema, definition, subject, relationship, syntax, and declaration validation | Unit/feature/scenario | Implemented grammar only; no automatic repair | Implemented | Preserve deterministic findings |
| Semantic index | Eight deterministic evidence-linked JSON files including subjects and artifact types | Unit/feature/scenario | No embeddings | Implemented | Consume in next slice |
| Database projection | Idempotent replacement, byte-complete canonical fingerprint, payload metadata, and run ledger | Unit/feature/scenario | Bootstrap tables; SQLite exercised | Implemented | Prove broader compatibility later |
| Rebuild | Confirmed semantic/database replacement | Feature/scenario | No cross-resource atomicity | Implemented | Improve failure recovery |
| Explain | Deterministic human/JSON orientation | Feature/scenario | Summary, not AI reasoning | Implemented | Add evidence queries |
| Compilation subject and chain | Explicit subject identifier/type, deterministic accepted-revision selection, ambiguity and cross-subject protection | Unit/feature/architecture | No as-of selection, revision pinning, cross-subject documents, or generic graph engine | Implemented | Preserve narrow responsibility |
| Authoring validation | Explicit artifact-type schemas, JSON Schema 2020-12 payload validation, strict document grammar, schema-backed field paths, deterministic findings, and narrow expected-source exception classification | Unit/feature/architecture | Dot paths only; no arrays, transforms, expressions, or calculated fields | Implemented | Inventory resolved document sets |
| Resolved Document IR | Ordered sections, evidence-bearing fields, explicit subject and primary artifact, complete-evidence resolution fingerprint, audience, actions, and attachments | Unit/feature/architecture | Validated bootstrap field grammar only | Implemented | Inventory document sets |
| Browser projection | Driver maps ResolvedDocument to structural browser data | Unit/feature/architecture | No rich layout or editing | Implemented | Keep presentation-only |
| Compile | Validates before inventory or targeted compilation; authoring errors stop, resolution absence remains unresolved, unexpected failures propagate | Feature/architecture | Unavailable later-stage evidence remains unresolved | Implemented | Add lifecycle document-set inventory |
| Workbench | Authenticated repository overview and resolved-document viewer | Feature | Read-only; no action execution | Implemented | Preserve projection boundary |
| GeNEi execution | Durable role and integration seam | Documentation | No autonomous actions/provider | Deferred | Keep deferred |
| x-document/x-change | Documented optional seams | Architecture checks | Not installed | Deferred | x-document adapter after resolved document |
| Production controls | None claimed | None | Audit/compliance/scale uncertified | Deferred | Deliberate later milestone |
