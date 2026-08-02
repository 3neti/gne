# Property Reservation MVP Acceptance Report

## Verdict

**Demonstrable MVP achieved.** GNE now proves one complete Property Reservation business case from immutable repository evidence through validation, subject-bound chain selection, lifecycle/document readiness, resolved meaning, x-document composition, and authenticated x-document-laravel delivery.

This is not a production-readiness claim. Authorization remains demonstration-scoped, verified-email policy is unresolved, and target-environment deployment proof remains outstanding.

## Scenario

- Subject: `PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001`
- Subject type: `PropertyReservation`
- Applicant: `Alicia Demonstration`
- Property: `LOT-DEMO-101`
- Corrected reservation amount: `PHP 51,000`
- Payment proof: `FICTIONAL-MANUAL-PROOF-101`
- Data classification: fictional demonstration data

The isolated sequence is:

```text
PropertyOffering + Application
→ Assessment
→ Invoice revision 1 (PHP 50,000)
→ Invoice revision 2 (PHP 51,000)
→ PaymentEvidence
→ PaymentApproval
→ Receipt
→ ReservationCertificate
```

## Stage results

The table records deterministic output from the isolated acceptance repository. `A`, `B`, `I`, `R`, and `C` abbreviate Application, Brochure, Invoice, Receipt, and Reservation Certificate definitions.

| Stage | Added evidence | Lifecycle / next | Resolved | Pending and first missing evidence | Browser document | Browser checksum / strong ETag |
|---|---|---|---|---|---|---|
| A | PropertyOffering, Application | `application_accepted` / `assessment_completed` | A, B | I: Invoice; R: PaymentApproval; C: Receipt | Application | `sha256:f21cedccc2cec5cb8d4b750fd3c8e868767974059107cfc603cbe9581f21eabf` |
| B | Assessment | `assessment_completed` / `invoice_accepted` | A, B | I: Invoice; R: PaymentApproval; C: Receipt | Application | unchanged `sha256:f21cedccc2cec5cb8d4b750fd3c8e868767974059107cfc603cbe9581f21eabf` |
| C1 | Invoice revision 1 | `invoice_accepted` / `payment_evidence_submitted` | A, B, I | R: PaymentApproval; C: Receipt | Invoice r1 | `sha256:745e87d517260f049edebe7b8902ef47d9db4deab8ad9d11362da8cb85115881` |
| C2 | Invoice revision 2 | `invoice_accepted` / `payment_evidence_submitted` | A, B, I | R: PaymentApproval; C: Receipt | Invoice r2 | `sha256:f5f65bfc1613432de7f5e84e2d193bdb7c6d822b33406b429ab91592f33dbac8` |
| D | PaymentEvidence | `payment_evidence_submitted` / `payment_approved` | A, B, I | R: PaymentApproval; C: Receipt | Invoice r2 | unchanged `sha256:f5f65bfc1613432de7f5e84e2d193bdb7c6d822b33406b429ab91592f33dbac8` |
| E | PaymentApproval | `payment_approved` / `receipt_accepted` | A, B, I | R: Receipt; C: Receipt | Invoice r2 | unchanged `sha256:f5f65bfc1613432de7f5e84e2d193bdb7c6d822b33406b429ab91592f33dbac8` |
| F | Receipt | `receipt_accepted` / `reservation_certified` | A, B, I, R | C: ReservationCertificate | Receipt | `sha256:e1aca7423f72ae0dcec0cd7c9c439b0162c694e86108fb9556b3ed15d59275fd` |
| G | ReservationCertificate | `reservation_certified` / none | A, B, I, R, C | none | Reservation Certificate | `sha256:6344351276b6fbe6d20826700c7812c8e6a2f0f0dd6a5cec41245484435dd011` |

Each strong ETag is the quoted browser checksum. Lifecycle stages have no gaps. The document-set fingerprint changes at each relevant evidence addition, while a document ETag changes only when that document's direct resolved evidence changes.

Payment evidence does not imply approval: at Stage D, Receipt remains pending on `PaymentApproval`. Payment approval does not invent an official receipt: at Stage E, Receipt remains pending on repository-authored `Receipt` evidence.

## Immutability proof

- Application revision 1 checksum remains `333ee03bab2c856d5783b6ca4ba06aae6cb2b0fe8659c6c861d7c26db77c2ffe` throughout.
- Invoice revision 1 checksum is `6de6b89fff49236eb23e63a6362e153c5ad13f452a4a7b6d4d95092536cb695e` before and after revision 2 is added.
- Invoice revision 2 is a separate file with checksum `ee557df13516a709e210ec3a3def2c96a4f498ebdf1a0516e5ffa415ded4b6fb`.
- Selection uses artifact identity and highest accepted revision, not filename or filesystem order.
- Duplicate identity/revision fixtures are rejected by validation; helper writes refuse to overwrite an existing path.
- The checked-out canonical `business/` checksum is asserted equal before and after the suite.

## Isolation proof

The copied repository retains `RESERVATION-000002` as unrelated evidence. Adding an unrelated accepted invoice revision changes that subject's document-set fingerprint but leaves the acceptance subject's set fingerprint, invoice bytes, checksum, and ETag unchanged. Acceptance browser output excludes `RESERVATION-000002`, `Ben Example`, and `75000`.

Cross-subject artifact references produce `artifact.cross_subject_reference`. Multiple accepted artifact identities satisfying one required type throw `AmbiguousArtifactSelection`; filesystem order is never used to choose one.

## Browser proof

- Authenticated GET returns exact x-document styled composition bytes with the declared HTML media type, deterministic filename, content length, checksum, and strong ETag.
- HEAD returns identical representation metadata and GET content length with no body.
- Matching strong and weak `If-None-Match` validators return bodyless 304.
- Invoice revision 2 presented with revision 1's validator returns 200, new bytes, and a new ETag.
- Repeated unchanged resolution returns identical bytes and ETag.
- Pending documents return 422 and never receive a partial fabricated representation.
- Existing route tests retain 404 behavior for unknown documents and subjects.

## Repository safety

`PropertyReservationAcceptanceRepository` exists only under `tests/Support`. It copies the minimum repository source into a unique system temporary directory, adds new files behind an explicit non-overwrite guard, and removes that copy after each test. Controllers, Vue, x-document, x-document-laravel, the operational database, and canonical business files never mutate lifecycle state.

## MVP readiness reassessment

- Before: 89–90%, product-flow proof incomplete.
- After: **Demonstrable MVP achieved**, with no remaining product-flow blocker.
- Closed blockers: full immutable lifecycle progression, invoice correction retention, payment-proof/approval distinction, receipt/certificate readiness, exact browser delivery, conditional refresh, and subject isolation.
- MVP-important: target-environment deployment smoke, deliberate subject authorization, verified-email decision, and operator demonstration polish.
- Post-MVP: production artifact-authoring UX, richer document drivers, binary attachments, background synchronization, and analytics.
- Enterprise-only: multi-organization governance, compliance certification, distributed materialization, and generalized workflow/policy engines.

The recommended next slice is **Target Deployment and Operator Demonstration Hardening**.
