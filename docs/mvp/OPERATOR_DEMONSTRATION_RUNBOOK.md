# Property Reservation Operator Demonstration Runbook

## Prerequisites

- PHP 8.4, Composer dependencies, Node dependencies, and a built frontend.
- Laravel Herd aligned with `APP_URL` (`http://gne.test` by default).
- The reviewed sibling x-document packages.
- Playwright Chromium (`npx playwright install chromium`).
- Optional FFmpeg for MP4 generation from the movie manifest.

The capture creates one ephemeral fictional operator, grants that user `view` only for `PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001`, uses the grant throughout the authenticated journey, then explicitly revokes the grant and removes the user. `User` does not implement `MustVerifyEmail`, so the `verified` middleware adds no email-verification requirement. Reports never record credentials, cookies, tokens, grant row IDs, or unrelated users.

## Reset, verify, and generate

Generated state is disposable. Remove only `.gne/storyboards/property-reservation-mvp/` for a clean recapture; never rewrite `business/`.

```bash
composer install --no-interaction
npm install
npm run build
php artisan migrate --force
php artisan gne:validate
php artisan gne:rebuild --force
php artisan gne:mvp:smoke
php artisan gne:storyboard property-reservation-mvp --capture --json
```

The runner derives its host from `APP_URL`, prepares stages in isolated repositories, grants the exact staged subject, opens a fresh browser context, captures `/login`, submits the actual login form, and preserves that session across protected GNE and x-document routes. Each frame verifies route, response, marker, authentication, and subject authorization before becoming `captured_and_verified`. Three lifecycle concepts remain labeled explanation frames. Cleanup revokes the grant before deleting the ephemeral user. Outputs are beneath `.gne/storyboards/property-reservation-mvp/`.

The final build order is capture, capture verification, finalized frame fingerprint, static HTML, Chromium PDF, narration/movie inventory, and report. Open `html/index.html` directly in a browser for the offline walkthrough; it uses only relative local assets and requires no server or JavaScript. The PDF is printed from `html/print.html`. Both final renditions reject planned, missing, or checksum-mismatched screenshots. A run without `--capture` is explicitly draft-only and removes any previous final PDF to prevent accidental reuse.

Expected final inventory: 25 PNG captures, 25 frame pages, five act pages, one index page, `html/manifest.json`, a 26-page PDF, narration, capture report, and movie build manifest. FFmpeg remains optional; when absent, use `movie/build.sh` later in an environment that provides it.

## Demonstrate and troubleshoot

Log in, open `/document-sets`, select a subject, and open resolved browser documents. Explain that repository artifacts are immutable facts, readiness is derived, payment evidence is not approval, and receipt/certificate availability requires separate accepted evidence.

- Wrong host: align `APP_URL`, clear configuration, and rebuild.
- Baseline failure: inspect exact commits from `gne:mvp:smoke`.
- Capture unavailable: install Playwright Chromium.
- `.gne` failure: grant the deployment user write access to `.gne/`, not `business/`.
- FFmpeg absent: retain the image sequence and deterministic movie manifest.

Verified here: local macOS with Herd at `http://gne.test`. Target production deployment and production authorization are not certified.
