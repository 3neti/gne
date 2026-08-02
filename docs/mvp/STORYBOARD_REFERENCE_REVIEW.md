# Storyboard Reference Review

Reviewed before the GNE storyboard architecture was implemented on 2026-08-02.

## AES reference

- Repository: `/Users/rli/PhpstormProjects/aes`
- Commit: `7acb8baaa7a1d9e56f80136f1e267d3a82113f0e`
- Files: `scripts/election-browser-walkthrough.mjs`, `scripts/election-walkthrough-storyboard.mjs`, `app/Election/Scenarios/BrowserWalkthroughEvidenceReport.php`, `tests/Feature/Election/BrowserWalkthroughRecorderTest.php`, and `docs/BROWSER_WALKTHROUGH_OPERATOR_MANUAL.md`.

AES drives a real authenticated browser with Playwright, records an ordered action log, captures full-page and presentation-sized images, hashes every artifact, and turns the same checkpoint inventory into JSON, HTML, and a Chromium-printed PDF. Its command tests fake the external process while verifying environment, failure preservation, and evidence registration.

GNE adopts the explicit checkpoint/frame metadata, real-page marker assertions, deterministic filenames, checksums, machine-readable report, and HTML-to-PDF projection. GNE does not copy election ceremonies, officer/token protocols, ballot controls, evidence locking, or election authority language. AES has no general movie compiler in the reviewed path; GNE therefore treats FFmpeg as optional and always emits a deterministic movie build manifest.

## x-change reference

- Repository: `/Users/rli/PhpstormProjects/packages/x-change`
- Commit: `38279c117ee72459d6cfc25b47d602386b7faade`
- Files: `src/ClaimWalkthrough/ClaimWalkthroughStoryboardBuilder.php`, `ClaimWalkthroughPdfRenderer.php`, `ClaimWalkthroughArtifactStore.php`, `ClaimPreviewJourneyManifestFactory.php`, `src/Console/Commands/Claim/ClaimWalkthroughCommand.php`, `scripts/claim-browser-walkthrough.mjs`, and `docs/claim-ux/storyboard-qa-runbook.md`.

x-change separates scenario definitions, deterministic run storage, browser capture, storyboard/report building, and PDF projection. Its preview-oriented walkthroughs make no-money and no-provider boundaries explicit and retain build-ready artifacts when browser capture is not the purpose of a run.

GNE adopts the small artifact-store boundary, explicit scenario/checkpoint inventory, safe dry planning, portable PDF/report outputs, and truthful unavailable status. GNE does not copy claims, Pay Codes, settlement/provider calls, splash/rider behavior, or x-change scenario semantics.

## GNE adaptation

The canonical demonstration choreography lives in `docs/mvp/storyboards/`; it is not business truth. `App\Domain\Storyboard` owns portable definitions and snapshots. Application/infrastructure collaborators prepare isolated Property Reservation repositories, observe the real compiler, and capture authenticated storyboard inspection routes. Generated images, PDF, narration, movie manifest, and reports live under `.gne/storyboards/` and are disposable.

The extraction seam is a definition loader, runner result, and capture process boundary. A future `3neti/x-storyboard` may own those generic concepts; Property Reservation state preparation and GNE route observation remain host adapters.
