# Implementation Status

| Intended capability | Current implementation | Test coverage | Limitations | Status | Next action |
|---|---|---|---|---|---|
| Canonical cognition | Root documentation and business source | Architecture checks | Bootstrap vocabulary only | Implemented | Preserve durability |
| Configuration | Version-1 YAML parser with safe relative paths | Unit/command | No extensible config language | Implemented | Evolve only from need |
| Discovery/validation | Repository-portable inventory with declaration-driven profiles, syntax, duplicates, and references | Unit/feature/scenario | Structural YAML/JSON checks; no full JSON Schema evaluation | Implemented | Add precise schema checks when required |
| Semantic index | Six deterministic evidence-linked JSON files | Unit/feature/scenario | No embeddings | Implemented | Consume in next slice |
| Database projection | Idempotent replacement, byte-complete canonical fingerprint, payload metadata, and run ledger | Unit/feature/scenario | Bootstrap tables; SQLite exercised | Implemented | Prove broader compatibility later |
| Rebuild | Confirmed semantic/database replacement | Feature/scenario | No cross-resource atomicity | Implemented | Improve failure recovery |
| Explain | Deterministic human/JSON orientation | Feature/scenario | Summary, not AI reasoning | Implemented | Add evidence queries |
| Resolved Document IR | Ordered sections, evidence-bearing fields, audience, actions, attachments, and compilation metadata | Unit/feature/architecture | Bootstrap field grammar and one artifact chain | Implemented | Validate grammar and multi-chain selection |
| Browser projection | Driver maps ResolvedDocument to structural browser data | Unit/feature/architecture | No rich layout or editing | Implemented | Keep presentation-only |
| Compile | Validation, index, document resolution, browser projection plan, and honest deferred drivers | Feature | Unavailable-evidence definitions remain unresolved | Implemented | Add explicit chain selection |
| Workbench | Authenticated repository overview and resolved-document viewer | Feature | Read-only; no action execution | Implemented | Preserve projection boundary |
| GeNEi execution | Durable role and integration seam | Documentation | No autonomous actions/provider | Deferred | Keep deferred |
| x-document/x-change | Documented optional seams | Architecture checks | Not installed | Deferred | x-document adapter after resolved document |
| Production controls | None claimed | None | Audit/compliance/scale uncertified | Deferred | Deliberate later milestone |
