# GNE

GNE is a repository-native **Business Compiler**: the business belongs to version-controlled files, while databases, browser views, documents, APIs, reports, and analytics are rebuildable projections.

The repository-native bootstrap now includes portable discovery, declaration-driven validation, semantic indexing, byte-complete fingerprinting, database materialization, subject-bound artifact chains, evidence-set document identity, and per-subject document/lifecycle inventories. It is not a production ERP, workflow engine, document renderer, or canonical artifact editor.

Repository validation now checks accepted payloads against explicit profile-owned JSON Schema 2020-12 declarations and validates repository-authored documents against the GNE-owned definition grammar before compilation. See [Diff Review Workflow](docs/development/DIFF_REVIEW_WORKFLOW.md) for incremental review packaging.

Structured validation JSON always includes every counted finding: warning-only repositories remain `valid: true`, report their warning count, and include the corresponding warning objects in `findings`. Expected malformed source becomes a finding; unexpected validator or compiler defects propagate.

Document readiness represents trustworthy business conditions: ordinary absent accepted evidence is `pending`, while ambiguous selection or cross-subject contamination fails validation or compilation. `Unavailable` and `not_applicable` are reserved for future explicit repository rules. Missing-evidence inventory currently reports the first unresolved direct source.

## Install and develop

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan test
```

Repository operations: `php artisan gne:validate`, `gne:index`, `gne:materialize`, `gne:rebuild --force`, `gne:explain`, `gne:compile`, `gne:documents`, and `gne:x-document:request`. Use `php artisan gne:documents --subject=RESERVATION-000001 --json` for a deterministic subject inventory. Use `php artisan gne:x-document:request --document=DOCUMENT-INVOICE --subject=RESERVATION-000001 --json` to inspect the versioned external transfer payload for one fully resolved document; it does not invoke x-document or render output.

`business/` is canonical source, `app/` interprets and projects it, and `.gne/` is disposable generated state. Configuration version 1 requires relative canonical/generated paths and an optional enabled-profile list in `gne.yaml`.

Canonical orientation: [GENEI.md](GENEI.md), [ARCHITECTURE.md](ARCHITECTURE.md), [GRAMMAR.md](GRAMMAR.md), [DECISION_REGISTER.md](DECISION_REGISTER.md), [COMPASS.md](COMPASS.md), and [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md).
