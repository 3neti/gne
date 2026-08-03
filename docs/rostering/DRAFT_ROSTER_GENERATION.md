# Draft Roster Generation

GNE supports an initial deterministic `balanced_greedy` draft for an empty `ready_for_generation` period. Hard eligibility is filtered before lexicographic preference, availability, remaining-hours, hours-ratio, assignment-count, preferred-off, and identifier ordering. Preview is mutation-free. Commit creates one run, one batch revision with one change per assignment, one generation audit, and transitions the period atomically. The draft remains editable and is not mathematically optimal.

Regeneration, fatigue, rest, overtime, on-call, multiple duties, publication, workbook import, and AI operation remain deferred.
