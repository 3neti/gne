# Property Reservation Operator Demonstration Runbook

## Prerequisites

- PHP 8.4, Composer dependencies, Node dependencies, and a built frontend.
- Laravel Herd aligned with `APP_URL` (`http://gne.test` by default).
- The reviewed sibling x-document packages.
- Playwright Chromium (`npx playwright install chromium`).
- Optional FFmpeg for MP4 generation from the movie manifest.

MVP policy A applies: authenticated demonstration users are sufficient. `User` does not implement `MustVerifyEmail`, so the `verified` middleware adds no email-verification requirement. Capture creates and removes an ephemeral fictional operator and never records credentials.

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

The runner derives its host from `APP_URL`, prepares stages in isolated repositories, captures authenticated read-only observation pages, validates visible markers, and removes its temporary user. Outputs are beneath `.gne/storyboards/property-reservation-mvp/`.

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
