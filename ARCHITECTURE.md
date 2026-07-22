# GNE Architecture

GNE is a standalone Laravel control plane around a repository-native compiler. Dependency direction is commands/controllers and infrastructure → domain services and values → repository evidence. Domain primitives have no Eloquent dependency.

```mermaid
flowchart TD
  R[Business Repository — canonical] --> D[Discovery and Validation]
  D --> S[Semantic Interpretation]
  S --> C[Compilation and Materialization]
  C --> DB[(Database projection)]
  C --> B[Browser projection]
  C --> DOC[Resolved Document]
  C --> API[APIs / Reports / Analytics]
```

`business/` contains accepted source. `app/` discovers, validates, interprets, compiles, and materializes. `.gne/` contains disposable indexes, caches, projections, and reports. Runtime sessions, queues, locks, OTPs, and temporary tokens are operational state. Git provides provenance and review.

Discovery reads `gne.yaml` without a database, validates safe paths, inventories profiles, scenarios, and artifacts, and emits findings. Semantic indexing writes deterministic evidence-linked JSON. Materialization replaces projection rows transactionally and records a fingerprinted run. Rebuild deletes only disposable semantic output and projection rows after confirmation. Failures leave canonical files untouched.

Every evidence path is normalized relative to the repository root supplied to discovery; the Laravel application root has no special meaning. Profiles declare their vocabulary, lifecycles, scenarios, policies, documents, and schemas, and validation resolves those declarations without imposing example-specific filenames. The canonical repository fingerprint hashes each ordered relative path together with its raw bytes for `gne.yaml`, `GENEI.md`, and every non-placeholder file under the configured business path.

Accepted artifacts are immutable and use stable repository identifiers plus revisions. Numeric IDs are implementation details. Canonical removal is reflected by projection replacement without erasing Git history.

## Integration seams

```mermaid
flowchart LR
  A[Accepted artifacts] --> R[ResolvedDocument]
  R --> D{DocumentProjectionDriver}
  D --> B[Browser]
  D -. optional .-> P[x-document / Adobe PDF]
  A -. request projection .-> X[GNE-to-x-change adapter]
  X -. optional .-> XC[x-change]
  E[Repository evidence] -. provider-independent context .-> G[GeNEi role]
```

Browser and PDF are peer projections. GNE knows no Adobe details; future x-document consumes `ResolvedDocument`. Settlement remains outside core and x-change optional. GeNEi may use different engines and must cite evidence.

## Resolved document intermediate representation

Repository-authored document definitions declare contributing artifact types, a primary artifact, audience, ordered semantic sections, fields, actions, and attachments. `ResolveDocument` selects accepted artifact revisions, resolves declared payload paths, and emits an immutable `ResolvedDocument`. Each resolved field carries its artifact identifier, artifact revision, source path, and value path.

The primary artifact anchors the document but does not define its identity alone. Resolution hashes the document-definition identifier, revision, and raw source fingerprint together with the deterministically ordered selected artifact identifiers, revisions, types, source paths, and raw source fingerprints. The resolved identifier derives from this resolution fingerprint. The same direct inputs produce the same identity; changing any selected revision or source bytes changes it; unrelated repository changes do not.

`ResolvedDocument` is the compiler intermediate representation: it contains business meaning but no HTML, Vue, Inertia, Tailwind, browser layout, PDF, or Adobe concepts. `BrowserProjectionDriver` maps that IR into a disposable structural browser projection without evaluating fields or adding business meaning. Future browser, PDF, Markdown, JSON, API, and mobile drivers are peers over the same IR.

Compilation planning reports expected `DocumentResolutionException` failures as unresolved definitions. Missing definitions are HTTP 404, while existing definitions lacking acceptable evidence are HTTP 422. Parser defects, infrastructure failures, type errors, and other unexpected exceptions propagate rather than being normalized into compilation results.

Laravel authentication protects the control plane. Public ceremonies may later use signed links, OTP, or transaction credentials without accounts. Organization, Repository, Membership, Role, and Authority need deliberate future modeling; generic teams are not enabled.
