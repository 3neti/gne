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

## Demonstrate and troubleshoot

Log in, open `/document-sets`, select a subject, and open resolved browser documents. Explain that repository artifacts are immutable facts, readiness is derived, payment evidence is not approval, and receipt/certificate availability requires separate accepted evidence.

- Wrong host: align `APP_URL`, clear configuration, and rebuild.
- Baseline failure: inspect exact commits from `gne:mvp:smoke`.
- Capture unavailable: install Playwright Chromium.
- `.gne` failure: grant the deployment user write access to `.gne/`, not `business/`.
- FFmpeg absent: retain the image sequence and deterministic movie manifest.

Verified here: local macOS with Herd at `http://gne.test`. Target production deployment and production authorization are not certified.
