# Property Reservation MVP

The MVP demonstrates that accepted Property Reservation evidence can be resolved by GNE, expressed by x-document, and delivered unchanged by x-document-laravel to an authenticated browser user.

The repeatable reconstructed demonstration is documented in `OPERATOR_DEMONSTRATION_RUNBOOK.md` and `PROPERTY_RESERVATION_STORYBOARD_REPORT.md`. It uses fictional isolated states and is neither an authoring workflow nor a production-readiness claim.

## Local installation with Laravel Herd

1. Confirm this repository directory is parked or linked through Laravel Herd.
2. Confirm the site resolves as `gne.test`.
3. Copy `.env.example` to `.env` and set `APP_URL=http://gne.test`. If the Herd site is secured, use `APP_URL=https://gne.test` instead.
4. Install PHP and frontend dependencies with `composer install` and `npm install`.
5. Generate the application key, migrate the database, and build the frontend with `php artisan key:generate`, `php artisan migrate`, and `npm run build`.
6. Run `php artisan gne:mvp:smoke` and confirm the repository, package baselines, contract, response factory, and route pass.
7. Register or use a local account, then log in through the configured Herd URL.
8. Open the Property Reservation workbench.
9. Follow **Open Unified Browser Document** for a resolved entry.

The completed invoice route is host-neutral:

```text
/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser
```

With the standard unsecured Herd configuration, open:

```text
http://gne.test/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser
```

A secured Herd site uses `https://gne.test`. Running `php artisan serve` is supported only as an alternative environment; if used, set `APP_URL` to that server's actual URL.

## Browser route

```text
GET|HEAD /subjects/{subject}/documents/{document}/browser
```

The route uses `auth` and `verified`, then requires an active host-owned `view` grant for the exact repository subject. The current `User` model does not implement Laravel's email-verification contract, so email verification is not yet an active additional check. Unknown subjects return `404`; known ungranted subjects return `403`; authorization occurs before resolution. This is deliberate MVP subject isolation, not organization ownership, enterprise RBAC, or multi-tenancy.

The default representation is `browser-composition-html-styled`. The strict query allowlist also accepts `browser-composition-html` and `browser-composition`. Unknown representations return 400. Unknown subjects or definitions return 404. Valid definitions with missing accepted evidence return 422.

Examples after login:

```text
/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser
/subjects/RESERVATION-000002/documents/DOCUMENT-APPLICATION/browser
```

`RESERVATION-000001` has a completed sample chain. `RESERVATION-000002` resolves Application and Invoice, while Receipt and Reservation Certificate remain pending and are not linked from the workbench.

## HTTP and refresh semantics

x-document-laravel owns the response. GET returns exact x-document bytes with the declared media type, inline deterministic filename, exact content length, private no-cache policy, `nosniff`, and a strong ETag. HEAD resolves once, returns the same metadata and GET length, and omits the body. Matching strong or weak `If-None-Match` values produce bodyless 304 responses. A malformed validator returns 400 through GNE-owned exception mapping.

A browser refresh resolves current repository evidence again. Unchanged evidence produces the same bytes and ETag, enabling 304. A direct accepted-evidence change produces a new resolved identity, output checksum, and ETag. Earlier artifact revisions remain canonical and are never overwritten by delivery.

## Runtime and dependency smoke

```bash
php artisan gne:mvp:smoke
php artisan gne:mvp:smoke --json
```

The smoke check validates repository source, confirms the required profile and subject, attests actual local package Git HEAD values, compiles the known invoice through the real x-document validator and styled browser runtime, checks the Laravel response-factory binding, and confirms route registration. It also reports the configured application URL, the named route's relative path, and a configuration-derived example document URL. Those values are informational; smoke success never depends on a particular host or scheme.

Reviewed baselines:

- `3neti/x-document`: `29853fae23939cba0b440db3ae04e351c499a78e`
- `3neti/x-document-laravel`: `b299d5bfbe7bdf93ecaf840431349804b676a6c7`

Baseline attestation needs local symlinked Composer path packages and Git. Browser delivery does not execute Git and continues to depend only on installed runtime classes.

## Repeatable end-to-end acceptance demonstration

Run the complete scenario with:

```bash
php artisan test --compact tests/Feature/PropertyReservationMvpAcceptanceTest.php
```

The suite copies `GENEI.md`, `gne.yaml`, and `business/` into a unique temporary repository. It then adds fictional accepted evidence in this order:

```text
PropertyOffering + Application
→ Assessment
→ Invoice revision 1
→ Invoice revision 2
→ PaymentEvidence
→ PaymentApproval
→ Receipt
→ ReservationCertificate
```

At each step it validates the copied repository, selects the subject-bound artifact chain, builds the lifecycle/document inventory, and resolves the currently available browser representation. The authenticated browser route is exercised against the temporary repository through the same GNE resolver, x-document runtime, and x-document-laravel response factory used by the application.

To perform the equivalent authoring ceremony manually in a disposable repository copy:

1. Begin with a new subject containing immutable `PropertyOffering` and `Application` files.
2. Run `php artisan gne:validate` and inspect `php artisan gne:documents --subject=<subject> --json` against that repository environment.
3. Add exactly one new artifact file for the next stage; never edit an accepted artifact in place.
4. Revalidate and rebuild disposable projections where the environment uses them.
5. Refresh the resolved browser route and compare its strong ETag.
6. For a correction, retain revision 1 and add revision 2 under the same artifact identifier.
7. Repeat through `ReservationCertificate`, then confirm all earlier files and checksums remain present.

The automated suite is the authoritative repeatable demonstration because it supplies the isolated repository root safely and cleans it afterward. The checked-out canonical `business/` tree is never used as mutable test state. Detailed deterministic evidence is recorded in the [Property Reservation Acceptance Report](PROPERTY_RESERVATION_ACCEPTANCE_REPORT.md).

## Troubleshooting

- A failed baseline check means the sibling package source is not at the reviewed commit; inspect it before updating Composer metadata.
- A 302 to login means the route requires an authenticated GNE session.
- A 404 means the subject or document definition is unknown.
- A 422 means the definition is valid but the selected subject lacks required accepted evidence, or repository validation prevents resolution.
- A 400 means the representation or `If-None-Match` syntax is invalid.
- PDF, active interactions, settlement, automatic polling, and browser editing are intentionally unavailable.
