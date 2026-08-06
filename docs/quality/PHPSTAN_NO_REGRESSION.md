# PHPStan No-Regression Gate

The reviewed project-wide target at commit `5c6cbd0c408a5b90a673c8daa693949f337622b4` is 135 legacy findings (the earlier characterization contained 304). This debt is reported, not suppressed.

Run `composer phpstan:no-regression`. The verifier compares normalized finding identities (`path`, identifier, message), requires the total not to exceed 135, rejects new identities, and requires newly created production files to be clean. It does not create or consume a PHPStan suppression baseline.

The canonical report language is:

> PHPStan full-project analysis completed with known legacy findings. No new findings were introduced relative to the reviewed baseline. All newly created production files are clean.

Do not state that project-wide PHPStan passed while the analyzer exits unsuccessfully. Intentional resolution of legacy findings reduces the count; a new finding is never offset by fixing a different one.
