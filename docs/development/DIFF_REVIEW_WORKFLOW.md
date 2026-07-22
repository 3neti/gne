# Diff Review Workflow

A Diff Review is a reproducible full patch between an exact reviewed base commit and a proposed target. It is sufficient for ordinary sequential slices when the reviewer has the base, every added, modified, deleted, renamed, and binary file is included, and the patch applies cleanly. Use a full repository ZIP for a first review, unavailable or divergent baselines, major reorganizations or dependency changes requiring snapshot parity, generated behavior outside the patch, takeover audits, or an explicit full-parity request.

## Uncommitted work

```bash
BASE_COMMIT=<full-reviewed-hash>
git status --short
git add -N .
git diff --binary --full-index "$BASE_COMMIT" -- > gne-slice-diff-review-YYYYMMDD.patch
git diff --stat "$BASE_COMMIT"
git diff --check "$BASE_COMMIT"
```

Intent-to-add makes untracked files visible without committing their content. Never prepare a review by destructively resetting the active worktree.

## Committed work

```bash
BASE_COMMIT=<full-reviewed-hash>
HEAD_COMMIT=<full-target-hash>
git diff --binary --full-index "$BASE_COMMIT" "$HEAD_COMMIT" > gne-slice-diff-review-YYYYMMDD.patch
git diff --stat "$BASE_COMMIT" "$HEAD_COMMIT"
git diff --check "$BASE_COMMIT" "$HEAD_COMMIT"
git log --oneline "$BASE_COMMIT..$HEAD_COMMIT"
```

## Apply-check and metadata

```bash
git worktree add /tmp/gne-diff-review "$BASE_COMMIT"
git -C /tmp/gne-diff-review apply --check /absolute/path/to/gne-slice-diff-review-YYYYMMDD.patch
git worktree remove /tmp/gne-diff-review --force

shasum -a 256 gne-slice-diff-review-YYYYMMDD.patch
wc -l gne-slice-diff-review-YYYYMMDD.patch
ls -lh gne-slice-diff-review-YYYYMMDD.patch
git status --short
```

Every submission states project, exact base and target, committed/uncommitted state, slice name, patch filename, verification results, diff stat, checksum, line count, size, worktree status, and apply-check result. `--binary --full-index` is mandatory; lockfiles, migrations, schemas, frontend files, renames, and deletions must not be omitted.
