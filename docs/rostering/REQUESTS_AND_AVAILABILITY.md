# Requests and Availability

Roster administrators can record submitted or explicitly accepted schedule requests, review conflicts, transition request status, and inspect the period availability matrix. Date ranges and non-contiguous dates share the same normalized date representation.

Hard prohibitions are accepted leave and unavailability. Explicit availability is positive eligibility evidence. Preferred work and preferred off remain soft. Only accepted records are effective; rejection or withdrawal preserves history while removing effect.

Routes under `/rostering/requests` are administrator-only. `/rostering/periods/{period}/availability` shows all 28 dates, staffing demand, availability counts, leave, preferences, and server-derived conflicts. It always states that no assignments have been generated.
