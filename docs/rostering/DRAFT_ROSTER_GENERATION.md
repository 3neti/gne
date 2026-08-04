# Draft Roster Generation

GNE supports an initial deterministic `balanced_greedy` draft for an empty `ready_for_generation` period. Hard eligibility is filtered before lexicographic preference, availability, remaining-hours, hours-ratio, assignment-count, preferred-off, and identifier ordering. Preview is mutation-free. Commit creates one run, one batch revision with one change per assignment, one generation audit, and transitions the period atomically. The draft remains editable and is not mathematically optimal.

Regeneration, fatigue, rest, overtime, on-call, multiple duties, publication, workbook import, and AI operation remain deferred.

The reporting projection is captured twice: revision 1 immediately after generation and revision 2 after the scenario's manual move. The finalized artifacts render real doctor assignments in four weekly date-card bands, a complete date-by-date list, and one doctor-by-date matrix per week. `G r1` and `M r2` retain generated and manually changed provenance. Staffing summaries remain distinct count reports and are never labeled as calendars.

Before preview, direct normalized staffing slots, credited hours, and active-doctor targets produce a feasibility result. Revision-1 quality then reports raw, allocated structural, and residual variance; assignment and weekend ranges; preference outcomes; explicit versus unspecified availability use; and descriptive consecutive-day patterns. Candidate traces preserve selected and next-best facts from the actual lexicographic ranking. Human projections use names only, while report JSON retains every stable identifier.
