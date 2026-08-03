# Requests and Availability

Roster administrators can record submitted or explicitly accepted schedule requests, review conflicts, transition request status, and inspect the period availability matrix. Date ranges and non-contiguous dates share the same normalized date representation.

Hard prohibitions are accepted leave and unavailability. Explicit availability is positive eligibility evidence, not permission to work. Under the provisional MVP policy, an active doctor with no accepted request for a date remains `unspecified` and eligible for generation; this policy requires department confirmation before production use. Preferred work and preferred off remain soft. Only accepted records are effective; rejection or withdrawal preserves history while removing effect.

Daily availability categories are mutually exclusive: effective explicit availability, unspecified, unavailable, or leave. Their counts reconcile to the active doctor count. A hard conflict retains every source request on the cell but contributes once to its effective blocking category. Eligibility is `explicit available + unspecified`; the eligible pool must meet the authored daily requirement before the period can become ready. That check is a necessary input condition, not proof that a valid or balanced roster exists.

The four-week calendar and doctor-by-date matrix are derived reporting projections. `A`, `U`, `L`, `PW`, `PO`, `-`, and `!` mean explicit availability, unavailable, leave, preferred work, preferred off, unspecified, and conflict. They are not assignments.

Routes under `/rostering/requests` are administrator-only. `/rostering/periods/{period}/availability` shows four calendar weeks, staffing demand, exclusive availability counts, a doctor matrix, preferences, and server-derived conflicts. It always states that no assignments have been generated.
