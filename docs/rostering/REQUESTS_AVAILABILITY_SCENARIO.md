# Requests and Availability Scenario

Run:

```bash
php artisan gne:roster:lifecycle:run --scenario=ANAESTHESIA-ROSTER-REQUESTS-AND-AVAILABILITY --artifact --json
```

The scenario creates ten fictional doctors and a 28-day period, authors staffing and required-hour targets, records each request type, proves a hard conflict blocks readiness, withdraws that conflict, retains soft warnings, reaches `ready_for_generation`, and finalizes report data. It creates no assignment. Default database state is rolled back; `--keep-state` is explicit.

Generated evidence lives under `.gne/reports/rostering/requests-and-availability/`. The static HTML fingerprint is the stronger semantic identity; Chromium PDF bytes may include environment-specific metadata.
