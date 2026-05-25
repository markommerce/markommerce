# Task 012: Final Verification

**Status**: completed
**Depends on**: 002, 003, 004, 005, 006, 007, 008, 009, 010, 011
**Retry count**: 0

## Description
Run the full quality gate after all cleanup tasks are complete: `composer test` (full suite), `phpcs` (lint check), `phpstan analyse` (static analysis), and `composer test:all` (destructive integration tests if Postgres is available). Confirm zero regressions and zero new failures introduced by the standard sweep. Document any deferred items in `_plan.md`.

## Context

This is the orchestration safety net — every preceding task ran its own meta-test, but only the full suite catches integration regressions (e.g., a renamed exception namespace breaking a test in an unrelated module, or a deleted module.php breaking module discovery).

- Related files:
  - `composer.json` (root) — defines the `test`, `test:all` scripts
  - `phpcs.xml` — lint rules
  - `phpstan.neon` — static analysis config
- Commands (run inside the Docker app container):
  ```bash
  docker compose -f ~/www/marko/compose.yaml exec -w /workspace/markommerce app composer test
  docker compose -f ~/www/marko/compose.yaml exec -w /workspace/markommerce app ./vendor/bin/phpcs
  docker compose -f ~/www/marko/compose.yaml exec -w /workspace/markommerce app ./vendor/bin/phpstan analyse
  docker compose -f ~/www/marko/compose.yaml exec -w /workspace/markommerce app composer test:all
  ```

## Requirements (Test Descriptions)

This task does not write new tests — it verifies existing ones. "Requirements" here are pass/fail gates:

- [ ] `composer test passes with zero failures`
- [ ] `composer test:all passes with zero failures (if Postgres is available; document if skipped)`
- [ ] `./vendor/bin/phpcs reports zero errors`
- [ ] `./vendor/bin/phpstan analyse passes at the configured level (8)`
- [ ] `all 12 packages are recognized and loaded by marko's module discovery`
- [ ] `no regression in code coverage (per CLAUDE.md, 80% minimum)`

## Acceptance Criteria
- All commands above complete successfully
- `_plan.md` status updated to `completed`
- Any deferred items (e.g., destructive tests skipped due to environment) documented in `_plan.md` under a "Deferred" section
- Branch is ready to open a PR against `develop`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
