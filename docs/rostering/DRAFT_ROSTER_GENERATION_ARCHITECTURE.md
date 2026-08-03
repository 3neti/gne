# Draft Roster Generation Architecture

## Purpose

The first generator produces a practical, deterministic, explainable draft without claiming mathematical optimality. It extends the existing roster application layer; it does not replace manual editing, shared validation, lifecycle controls, revision history, or period-scoped audit history.

## Generator contract

`RosterGenerator` accepts a normalized `RosterGenerationInput` and a resolved `ResolvedRosterPolicy`, then returns an immutable `GeneratedRosterResult`. `BalancedGreedyRosterGenerator` is the initial implementation. It performs no persistence and has no controller, Vue, YAML, audit, revision, or Eloquent-write responsibility.

## Inputs

`BuildRosterGenerationInput` converts application facts into stable domain values:

- period identifier, status, date boundary, and ordered roster days;
- active doctors with identifiers and standard daily credited hours;
- explicit required-hour targets;
- effective availability, blocking leave/unavailability, and preferences;
- daily required headcounts;
- existing-assignment count;
- resolved policy fingerprint and provenance.

Repository interpretation remains upstream. The generator never reads raw YAML, workbook cells, audit history, controllers, or frontend props.

## Policy

`ResolveRosterPolicy` resolves the repository-authored anaesthesia policies into `ResolvedRosterPolicy`. Version 1 requires active doctors, complete daily requirements, explicit hour targets, accepted-request validation, leave and unavailability prohibition, one assignment per doctor/date, standard-day duties, under-target priority, explicit-availability preference, preferred-work preference, preferred-off avoidance, assignment-count balance, and stable doctor-identifier tie-breaking.

## Eligibility

Candidates are removed before ranking when inactive, on accepted leave, accepted as unavailable, already assigned on the date, outside the period, or incompatible with the supported standard-day duty and positive credited hours. Hard eligibility can never be outweighed by preferences.

Unspecified availability remains provisionally eligible when the doctor is active and has no accepted blocking evidence. Explicit availability is positive evidence and ranks above unspecified availability; unspecified is never described as a request.

## Ranking and deterministic ordering

Days are evaluated by ascending date. For each required slot, eligible candidates are ordered lexicographically by:

1. accepted preferred-work for the date;
2. accepted explicit availability for the date;
3. positive remaining required hours, then greater remaining hours;
4. lower assigned-hours-to-target ratio;
5. lower assignment count;
6. absence of accepted preferred-off;
7. stable doctor identifier.

Totals are recalculated after every proposed assignment. Lists preserve authored date order; maps and findings are canonically ordered before hashing or serialization. Randomness, timestamps, database insertion order, and filesystem order are excluded.

## Outputs and explanations

`GeneratedRosterResult` contains ordered proposed assignments, daily staffing and doctor-hour summaries, preference outcomes, missing slots, validation findings, explanations, status, and a SHA-256 fingerprint derived only from policy, normalized inputs, and generator identity/version.

Each assignment explanation retains structured reason codes and ranking facts, including eligibility, required slot, hours remaining before selection, assignment count before selection, availability evidence, preference evidence, and stable tie-break facts. Unhonored preferences and shortages state only causes demonstrated by the algorithm.

## Preview

`PreviewDraftRosterGeneration` runs the same input, policy, generator, and proposal validation path as commit. It writes no assignment, generation run, revision, audit entry, or lifecycle state. Repeated previews over unchanged inputs return the same proposal and fingerprint.

## Batch persistence and atomicity

`GenerateDraftRoster` authorizes and checks `ready_for_generation`, input validity, and the absence of existing assignments. It rejects a proposal containing mandatory errors. Inside one database transaction, `PersistGeneratedRosterBatch` creates:

- one committed generation run;
- all assignments with source `generated`;
- one top-level roster revision;
- one revision-change row per generated assignment;
- one `roster_generation.completed` period-scoped audit entry;
- the generated validation snapshot;
- the transition from `ready_for_generation` to `generated`.

Any assignment, revision, audit, or transition exception rolls the whole operation back. Initial generation never merges with or overwrites an existing roster.

## Revision and audit semantics

One generation command creates one top-level revision containing many assignment changes. Batch persistence deliberately does not call `CreateRosterAssignment`, because that manual action creates per-assignment revision and audit evidence. The generation audit summarizes the run, fingerprints, counts, revision, validation, staffing, and hours. Assignment-level evidence lives in revision changes and generation explanations.

## Lifecycle and manual editing

Preview leaves `ready_for_generation` unchanged. Successful commit transitions exactly once to `generated`. The existing add, remove, move, and replace actions remain available afterward and create later manual revisions. Publication and regeneration remain deferred.

## UI

The authenticated generation workbench is server-prepared and read-only until the administrator explicitly previews or commits. It shows assumptions, readiness, proposed calendar, staffing, hours, preferences, findings, explanations, and fingerprints. Vue submits typed Wayfinder routes and contains no ranking or eligibility logic.

## Scenario and artifacts

`ANAESTHESIA-DRAFT-ROSTER-GENERATION` creates deterministic fictional inputs, proves identical previews and zero preview persistence, commits one generation batch, validates it, performs one later manual correction, and rolls operational state back by default. JSON and detailed HTML retain complete explanations; the PDF is an operator-focused generated-draft review artifact.

## Limitations and replacement seam

The generator is greedy rather than optimal. It supports one primary standard-day assignment per doctor/date, provisional unspecified eligibility, and initial generation only. It has no fatigue, rest, overtime, on-call, second-call, theatre, payroll, publication, workbook-import, AI, or cross-period logic.

A future solver may replace `BalancedGreedyRosterGenerator` behind `RosterGenerator` if it consumes the same normalized input and policy and returns the same result contract. Persistence, validation, revision, audit, lifecycle, UI, and artifacts must remain outside that solver.
