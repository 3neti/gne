# Roster Policy Decision Workflow

The repository is the closed option registry. Each option supplies its internal value, human label, description, operational impact, and confirmation requirement. The administrator UI renders radio cards; unknown keys and values fail closed.

Confirmation requires decision authority, evidence reference, notes, and an effective start with an optional valid end. A later decision atomically closes the earlier effective revision on the preceding day and audits confirmation, scheduling, and supersession. A conflicting current or scheduled window is rejected. Historical selected values are never overwritten.

Impact preview is read-only. It compares current and candidate fingerprints and explains generation, validation, and quality consequences without persisting a decision or changing a roster.

Before confirmation, the application validates option support, typed configuration, and the effective window. Missing configuration and unsupported dependencies are shown explicitly. The UI warns that a bounded revision permanently supersedes earlier coverage and recommends scheduling a later revision when coverage must continue.
