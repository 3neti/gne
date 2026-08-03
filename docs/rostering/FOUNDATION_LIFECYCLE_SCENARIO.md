# Foundation Lifecycle Scenario

`ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE` is repository-authored under the anaesthesia profile and executed with:

```bash
php artisan gne:roster:lifecycle:run --scenario=ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE --json
```

The runner accepts only five named operations. It proves that warnings do not block readiness, errors do, assignment and audit persist atomically, duplicate assignments are rejected without mutation audit, and audit failure rolls assignment creation back. Default execution is enclosed in a database transaction and rolled back; `--keep-state` is the only explicit persistence mode.

The missing-day error is a controlled scenario fixture used only to exercise the real validator and transition service. YAML cannot select PHP classes, issue SQL, invoke shell commands, or mutate model status. Reports are deterministic and omit generated identifiers and timestamps.

Warnings describe remediable foundation conditions. Only error findings prevent `ready_for_generation`. The runner is a development and CI proof, not a workflow engine, generator, optimizer, import mechanism, or production UI.
