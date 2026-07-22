# GNE

GNE is a repository-native **Business Compiler**: the business belongs to version-controlled files, while databases, browser views, documents, APIs, reports, and analytics are rebuildable projections.

The repository-native bootstrap, portability hardening, Resolved Document vertical slice, and explicit compilation-subject selection are complete. GNE demonstrates discovery, declaration-driven validation, semantic indexing, byte-complete fingerprinting, database materialization, subject-bound artifact-chain selection, complete-evidence-set document identity, and a peer browser projection. It is not a production ERP, workflow engine, document renderer, or canonical artifact editor.

Repository validation now checks accepted payloads against explicit profile-owned JSON Schema 2020-12 declarations and validates repository-authored documents against the GNE-owned definition grammar before compilation. See [Diff Review Workflow](docs/development/DIFF_REVIEW_WORKFLOW.md) for incremental review packaging.

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

Repository operations: `php artisan gne:validate`, `gne:index`, `gne:materialize`, `gne:rebuild --force`, `gne:explain`, and `gne:compile`.

`business/` is canonical source, `app/` interprets and projects it, and `.gne/` is disposable generated state. Configuration version 1 requires relative canonical/generated paths and an optional enabled-profile list in `gne.yaml`.

Canonical orientation: [GENEI.md](GENEI.md), [ARCHITECTURE.md](ARCHITECTURE.md), [GRAMMAR.md](GRAMMAR.md), [DECISION_REGISTER.md](DECISION_REGISTER.md), [COMPASS.md](COMPASS.md), and [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md).
