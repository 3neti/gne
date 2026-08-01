# Property Reservation MVP

The MVP demonstrates that accepted Property Reservation evidence can be resolved by GNE, expressed by x-document, and delivered unchanged by x-document-laravel to an authenticated browser user.

## Browser route

```text
GET|HEAD /subjects/{subject}/documents/{document}/browser
```

The route uses the existing `auth` and `verified` middleware. Its temporary MVP gate permits authenticated local GNE users to view repository-authored demonstration subjects. It does not implement organization ownership, customer access, or multi-tenant isolation.

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

The smoke check validates repository source, confirms the required profile and subject, attests actual local package Git HEAD values, compiles the known invoice through the real x-document validator and styled browser runtime, checks the Laravel response-factory binding, and confirms route registration.

Reviewed baselines:

- `3neti/x-document`: `29853fae23939cba0b440db3ae04e351c499a78e`
- `3neti/x-document-laravel`: `b299d5bfbe7bdf93ecaf840431349804b676a6c7`

Baseline attestation needs local symlinked Composer path packages and Git. Browser delivery does not execute Git and continues to depend only on installed runtime classes.

## Troubleshooting

- A failed baseline check means the sibling package source is not at the reviewed commit; inspect it before updating Composer metadata.
- A 302 to login means the route requires an authenticated GNE session.
- A 404 means the subject or document definition is unknown.
- A 422 means the definition is valid but the selected subject lacks required accepted evidence, or repository validation prevents resolution.
- A 400 means the representation or `If-None-Match` syntax is invalid.
- PDF, active interactions, settlement, automatic polling, and browser editing are intentionally unavailable.
