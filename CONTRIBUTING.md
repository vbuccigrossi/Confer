
## ⚙️ **`CONTRIBUTING.md`**

```markdown
# Contributing Guide — Latch

This document defines how contributors (including Claude Code) must structure their work.

---

## 1. Workflow Overview

All development follows **feature-branch workflow**:

```

main ──► dev ──► feat/<phase>-<slug>

````

No direct commits to `main`.

---

## 2. Local Setup

```bash
make bootstrap   # one-time project setup
make up          # start full stack
make test        # run suite
````

Run `make fresh` before major schema changes.

---

## 3. Development Rules

* Use **PSR-12** coding style and run auto-formatting before PR.
* Write all migrations and seeds idempotently.
* New API endpoints require:

  * Controller
  * Policy (if applicable)
  * Form Request validation
  * Feature tests
  * OpenAPI spec update (`api_spec_stub.yaml`)

---

## 4. Testing Before Commit

Before pushing:

```bash
make lint
make test
```

CI enforces:

* No test failures
* Coverage ≥85%
* Zero PHPStan or ESLint errors
* No uncommitted Claude-Tmp files

---

## 5. Pull Request Checklist

✅ Feature branch named properly
✅ Descriptive title & linked issue
✅ Unit + integration tests added
✅ Docs in `/Claude-Docs/` updated
✅ `CHANGELOG.md` entry
✅ Rollback plan noted

---

## 6. Code Review Standards

Reviewers check for:

* Correct data validation and policy usage
* No N+1 queries
* Reusable form requests and resources
* Accurate docstrings
* Proper event broadcasting and tests

---

## 7. Commit Message Format

```
feat(auth): add workspace invite acceptance

Refs: #24
Change-Type: minor
Provenance: origin=claude run=abc123 time=2025-11-07T18:00Z
```

---

## 8. Dependency Policy

* All new composer/npm deps must be reviewed for license and maintenance.
* Use permissive licenses only (MIT, Apache-2.0, BSD-2/3).
* Record license in `/Claude-Docs/licenses.md`.

---

## 9. Merging

Only after:

* CI green
* Review approval
* Documentation updated
* No Claude-Tmp artifacts

---

## 10. Post-Merge

Tag releases with `make tag-release` and update the changelog.

````

