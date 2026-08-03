# GNE

GNE is a repository-native **Business Compiler**: the business belongs to version-controlled files, while databases, browser views, documents, APIs, reports, and analytics are rebuildable projections.

The anaesthesia rostering foundation is available to deliberately designated roster administrators at `/rostering`. It normalizes doctors, roster periods/days, explicit staffing headcounts, explicit doctor-period required hours, early lifecycle transitions, audit evidence, and a one-primary-assignment persistence seam. Run `php artisan db:seed --class=AnaesthesiaRosteringDemoSeeder` for fictional demonstration data and `php artisan gne:roster:validate --period=ROSTER-2026-09`. It does not generate, import, publish, or calculate payroll.

The repository-native bootstrap now includes portable discovery, declaration-driven validation, semantic indexing, byte-complete fingerprinting, database materialization, subject-bound artifact chains, evidence-set document identity, and per-subject document/lifecycle inventories. It is not a production ERP, workflow engine, document renderer, or canonical artifact editor.

Repository validation now checks accepted payloads against explicit profile-owned JSON Schema 2020-12 declarations and validates repository-authored documents against the GNE-owned definition grammar before compilation. See [Diff Review Workflow](docs/development/DIFF_REVIEW_WORKFLOW.md) for incremental review packaging.

Structured validation JSON always includes every counted finding: warning-only repositories remain `valid: true`, report their warning count, and include the corresponding warning objects in `findings`. Expected malformed source becomes a finding; unexpected validator or compiler defects propagate.

Document readiness represents trustworthy business conditions: ordinary absent accepted evidence is `pending`, while ambiguous selection or cross-subject contamination fails validation or compilation. `Unavailable` and `not_applicable` are reserved for future explicit repository rules. Missing-evidence inventory currently reports the first unresolved direct source.

## Install and develop

Local development is standardized on Laravel Herd. Park or link this repository so it resolves as `gne.test`, copy `.env.example`, and keep `APP_URL` aligned with the Herd site (`http://gne.test`, or `https://gne.test` after securing it). The application route remains host-independent; `php artisan serve` is an optional alternative environment, not the documented default.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan test
```

Repository operations: `php artisan gne:validate`, `gne:index`, `gne:materialize`, `gne:rebuild --force`, `gne:explain`, `gne:compile`, `gne:documents`, `gne:x-document:request`, `gne:x-document:compile`, and `gne:mvp:smoke`. Use `php artisan gne:documents --subject=RESERVATION-000001 --json` for a deterministic subject inventory. Use `php artisan gne:x-document:request --document=DOCUMENT-INVOICE --subject=RESERVATION-000001 --json` to inspect the producer payload. Invoke the real runtime with:

```bash
php artisan gne:x-document:compile \
  --document=DOCUMENT-INVOICE \
  --subject=RESERVATION-000001 \
  --representation=browser-composition-html-styled
```

Local development resolves `3neti/x-document` and `3neti/x-document-laravel` from sibling `../packages/` path repositories with symlinks. Reviewed integration baselines are recorded in `IMPLEMENTATION_STATUS.md`; package source is never copied into GNE.

Authenticated GNE users can open `GET|HEAD /subjects/{subject}/documents/{document}/browser` only when the host database contains an active `view` grant for that exact repository-native Compilation Subject. Authorization runs before resolution and x-document invocation; an unknown subject is `404`, while a known ungranted subject is `403`. The subject inventory is filtered before serialization, and global repository workbenches require the deliberately assigned operator flag. The route remains in the existing `auth` + `verified` group, although the current `User` model does not yet opt into Laravel's email-verification contract. GNE resolves current repository evidence, x-document produces the representation, and x-document-laravel owns exact HTTP bytes and metadata. See [Property Reservation MVP](docs/mvp/PROPERTY_RESERVATION_MVP.md).

Operators manage the intentionally narrow MVP grant model with `php artisan gne:subject-access:grant --user=operator@example.test --subject=RESERVATION-000001`, `gne:subject-access:list`, and `gne:subject-access:revoke` using the same user and subject options. Add `--json` for automation. Only `view` exists; there are no wildcards, inferred ownership rules, enterprise roles, or multi-tenant claims.

For the completed example, log in at the configured `APP_URL` and open `/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser`. On the standard unsecured Herd site, the absolute URL is `http://gne.test/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser`; a secured Herd site uses HTTPS.

`php artisan gne:mvp:smoke` attests the actual Git HEAD of each local package against its reviewed commit and compiles the canonical invoice through the real contract and browser runtime. Attestation runs only in diagnostics, smoke checks, and tests—not on browser requests.

The x-document contract `1.0` is closed under `resources/gne/contracts/x-document/1.0/`. Its standalone resolved-document schema is authoritative and referenced by the request schema through stable versioned IDs. GNE's runtime adapter crosses this boundary as canonical JSON and lets the real x-document package validate and express it; no repository service or internal IR is passed to the package.

The Property Reservation MVP now has a complete isolated acceptance proof from application through reservation certification. It adds immutable fictional artifacts stage by stage, exercises real validation, chain selection, lifecycle inventory, x-document composition, authenticated GET/HEAD/conditional delivery, revision retention, and subject isolation without modifying canonical repository source. See the [acceptance report](docs/mvp/PROPERTY_RESERVATION_ACCEPTANCE_REPORT.md).

`business/` is canonical source, `app/` interprets and projects it, and `.gne/` is disposable generated state. Configuration version 1 requires relative canonical/generated paths and an optional enabled-profile list in `gne.yaml`.

Canonical orientation: [GENEI.md](GENEI.md), [ARCHITECTURE.md](ARCHITECTURE.md), [GRAMMAR.md](GRAMMAR.md), [DECISION_REGISTER.md](DECISION_REGISTER.md), [COMPASS.md](COMPASS.md), and [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md).

## Repository-native storyboard

`php artisan gne:storyboard property-reservation-mvp --capture --json` reconstructs fictional acceptance states in isolated repositories, submits the real login form once, and captures the journey through one continuing authenticated browser context using `APP_URL`. Production frames use actual GNE or x-document routes; the three remaining narrative-only frames are visibly labeled explanations. A capture is final only after its route, response, session, visible marker, screenshot checksum, and byte length are verified. Final capture produces the offline `gne-storyboard-html/1.0` site at `.gne/storyboards/property-reservation-mvp/html/index.html`, then prints its finalized screenshot inventory to PDF and reuses that inventory for the movie build package. Running without `--capture` is draft-only and never claims final HTML or PDF. Canonical choreography is under `docs/mvp/storyboards/`; disposable output is under `.gne/storyboards/`. See the [operator runbook](docs/mvp/OPERATOR_DEMONSTRATION_RUNBOOK.md) and [storyboard report](docs/mvp/PROPERTY_RESERVATION_STORYBOARD_REPORT.md).
