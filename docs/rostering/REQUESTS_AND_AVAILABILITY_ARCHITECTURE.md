# Requests and Availability Architecture

Doctor schedule requests are mutable host records, never repository artifacts. A request belongs immutably to one doctor and roster period and expands one range or a non-contiguous selection into normalized request-date rows. Its closed type vocabulary is `available`, `unavailable`, `leave`, `preferred_work`, and `preferred_off`; status is `submitted`, `accepted`, `rejected`, or `withdrawn`.

Application actions exclusively create, edit, and transition requests. Creation, status changes, and safe audit evidence share database transactions. Only accepted requests affect the derived availability calendar. Rejected and withdrawn requests remain historical. New requests require an active doctor and at least one unique in-period date.

Effective state uses deterministic precedence: leave, unavailable, available, then soft preferences. Precedence never hides a conflict. Available/unavailable and leave/available are errors; leave/preferred-work, unavailable/preferred-work, and opposing preferences are warnings. Errors block `ready_for_generation`; warnings remain visible in the transition result and administrator UI.

`ResolveDoctorAvailability` is the read-only compiler from accepted operational input to per-doctor/date state, daily counts, and explanations. Vue receives this projection and performs no business inference. The availability matrix is explicitly not an assignment roster.

The repository-authored scenario names only allowlisted operations. Its finalized result produces disposable JSON, static HTML, and a Chromium-printed PDF under `.gne/reports/rostering/`. Operational rows roll back by default while derived artifacts survive. Doctor self-service, OpenAPI mutation, import, multiple duties, on-call logic, optimization, generation, and publication remain deferred.
