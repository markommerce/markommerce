# Plan: Integration Test Suite Pruning & Reclassification

## Created
2026-06-09

## Status
completed

## Objective
Prune the markommerce Feature/integration test suite so every DB-backed test (`->group('integration-destructive')`) earns its Postgres/boot cost, and reclassify mislabeled non-DB tests out of the integration group/`Feature/` directory — without losing real coverage. No production (`src/`) changes.

## Related Issues
none

## Discovery Notes
A 5-cluster review of ~65 Feature files (~19k lines) found the suite is ~2–3× larger than its value, with waste concentrated in six recognizable patterns (not scattered). Verified facts that shape execution:

- **Test discovery is by glob** (`phpunit.xml` → `packages/*/tests`), recursive. Pest test files carry **no `namespace` declaration**, and PSR-4 maps `Markommerce\{Pkg}\Tests\ => tests/`. Therefore moving a file `tests/Feature/X.php → tests/Unit/X.php` does **not** break discovery or autoloading, and `dirname(__DIR__, 2)` (package root) is preserved for same-depth moves. Files nested deeper (e.g. `Feature/Sorting/X.php` using `dirname(__DIR__, 4)`) must have their `dirname` depth recomputed if relocated.
- Only **`config/tests/Feature/ModulePhpTest.php`** is genuinely *mis-tagged* (15 `integration-destructive` tags, zero DB harness usage) — stripping the group is a real cost fix. The other non-DB Feature files (`scope/ScopedOverrides*`, `scope/DefaultScopeResolution`, `scope-pgsql/AutoMigration`, `currency-market`/`tax-market` BootContribution, `criteria/ModuleBindings`, `frontend/ModuleBoot`) carry **no** group tag — they already run in the fast `composer test` suite; relocating them is tidiness.
- `composer test` = `--exclude-group=integration-destructive`; `composer test:integration` = `--group=integration-destructive`. The harness is `Markommerce\Testing\IntegrationTestCase` + `StoreProfile`; canonical cross-profile sort coverage lives in `packages/testing/tests/Feature/InvariantMatrixTest.php`.

The `packages/testing` harness suite is **healthy** and is kept (only trivial internal merges).

## Scope

### In Scope
- Delete development-milestone "Tier1/2/3 EndToEnd" tests superseded by focused tests.
- Delete verbatim-duplicate tests, source-text grep tests (`file_get_contents(module.php)->toContain(...)`), and "exactly N statements" count milestones.
- Collapse pagination/sort/repository integration cases that re-prove unit tests or `InvariantMatrixTest`.
- Delete brittle demo/theme full-HTML snapshot tests; keep one smoke + the enable/disable gate per demo.
- Reclassify mislabeled tests: strip `integration-destructive` where there's no DB; relocate pure non-DB tests `Feature/ → Unit/`.
- Move genuinely-unique non-DB assertions into existing unit tests rather than deleting (e.g. loud-exception cases).
- Remove now-orphaned test-support/helper files left after deletions.

### Out of Scope
- Any change to `src/` production code.
- Changes to the `packages/testing` harness source.
- Adding new test coverage (this is pruning, not expansion). Exception: relocating an existing assertion into a unit test.
- Touching tests not named in the task files.

## Success Criteria
- [x] `composer test` (unit suite) green.
- [x] `composer test:integration` (DB suite) green.
- [x] `./vendor/bin/phpcs` clean and `php -d memory_limit=2G ./vendor/bin/phpstan analyse` green for touched files.
- [x] Every surviving `integration-destructive` test boots the DB harness (no test tagged integration without a real DB need).
- [x] No orphaned helper/support files or dangling `use`/`require` references remain.
- [ ] Net reduction on the order of ~5,000–6,000 test lines; no loss of a distinct risk-guarding case (each deleted case has a named unit/integration equivalent recorded in the task file).
- [x] All tests passing; code follows project standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | catalog-storefront render-suite prune (7 Category* + Tier1 + ProductGridTemplate) | - | completed |
| 002 | catalog core repository/tree/seeder integration trims | - | completed |
| 003 | catalog core service/factory/sort trims (Paginated 732ln, FixtureFactories, RepositoryImplementations) | - | completed |
| 004 | config family cleanup (ModulePhpTest regroup, source-greps, emitter milestones, config-scope Tier2) | - | completed |
| 005 | scope family cleanup (scope-pgsql PostgresIntegration 801→~300, ScopedOverrides merge+move, AutoMigration/DefaultScope move) | - | completed |
| 006 | market & price-index cluster (catalog-scope Tier2 delete, catalog-market Tier3 refocus, price-sort dedup, boot downgrades) | - | completed |
| 007 | demo/theme/misc prune (theme-blank-demo, theme-blank, frontend-demo, layout-demo, criteria/frontend boot) | - | completed |
| 008 | packages/testing harness suite internal merges (minor) | - | completed |
| 009 | Full-suite verification + orphan sweep (composer test:all, phpcs, phpstan, docs counts) | 001,002,003,004,005,006,007,008 | complete |

## Architecture Notes
- **Parallelism**: tasks 001–008 touch disjoint package `tests/` trees. Within `packages/catalog`, tasks 002 and 003 are disjoint **at the file level** (verified): task 002 owns `RepositoryImplementationsTest.php`, `Repositories/*`, `CategoryTreeIntegrationTest.php`, `CatalogSeederTreeTest.php`, and (keep-only, no edit) `Sorting/CategorySortOrderRegistryBootTest.php` + `Pricing/PriceContributorRegistryTest.php`; task 003 owns `Services/CategoryAssignmentServicePaginatedIntegrationTest.php`, `Factories/FixtureFactoriesTest.php`, `Sorting/EndToEndPositionSortOrderIntegrationTest.php`, `Pricing/PriceResolverTest.php`. They share the `Sorting/` and `Pricing/` *directories* but never the same file. They are safe to run in parallel. Task 009 is the join point.
- **Verified discovery/relocation facts** (do not re-derive): `phpunit.xml` discovers `packages/*/tests` recursively with no Unit-vs-Feature testsuite split; every per-package `tests/Pest.php` is an **empty stub** (no `uses()` bindings to break on a move); there are **no** `tests/Datasets.php` files and **no** `tests/Feature/Helpers/` directories anywhere in the repo (task 009's sweep should treat those as "confirm-absent", not "expect-and-remove"). `tests/Support/` factory dirs exist only in `catalog`, `scope`, and `catalog-market`. Shared dataset/helper closures live **inside** individual test files (e.g. `InvariantMatrixTest.php`, `EndToEndPositionSortOrderIntegrationTest.php`), so deleting a *case* that uses a same-file helper must check whether a surviving case still uses that helper before removing it.
- **Depth-shift on relocation/merge**: `Feature/X.php → Unit/X.php` (both one level under `tests/`) preserves `dirname(__DIR__, N)` — safe. But **merging a file into one at a different nesting depth changes `dirname` depth**: e.g. folding top-level `catalog-market/tests/Feature/PriceAmountScopeResolutionTest.php` (uses `dirname(__DIR__, 2|3)`) into `Feature/Pricing/ScopedProductBasePriceProviderTest.php` (uses `dirname(__DIR__, 3|4)`) requires incrementing every relocated `dirname` depth by 1. Recompute on any cross-depth move.
- **Reclassification mechanic (default chosen)**: (a) strip `->group('integration-destructive')` from any test with no DB harness; (b) physically relocate a *pure* non-DB test from `tests/Feature/` to `tests/Unit/` only when it is genuinely unit-level (no container boot) — recompute `dirname(__DIR__, N)` if nesting depth changes; (c) leave no-DB-but-boots-a-Marko-container tests (BootContribution/ModuleBoot) in `Feature/` but ensure they are untagged. Prefer the smallest correct change.
- **Deletion discipline**: before deleting a case, the task file records the named surviving equivalent (unit test or `InvariantMatrixTest` case). Do not delete a case whose equivalent cannot be located — flag it in `## Implementation Notes` instead.
- **TDD-fit caveat**: this is a deletion/refactor plan, not feature TDD. There is **no Red phase** — a `tdd-worker` must NOT try to write a failing test first. Each task's "Requirements" are *verification assertions about the resulting suite*, not test cases to author. The execution loop for every task here is:
  1. Run the targeted suite **before** editing and confirm green (establishes the baseline — substitutes for "Red").
  2. Apply the deletions/relocations/merges named in the task.
  3. For each deleted case, **grep the named unit/InvariantMatrix equivalent and confirm it exists** (open the file, confirm the assertion matches) before removing the integration case. If the equivalent cannot be located or asserts something materially different, **keep the case and record it in `## Implementation Notes`** rather than deleting.
  4. Run the targeted suite **after** editing and confirm still green (the "Green").
  5. Run phpcs on touched files.
  This loop is the deliverable; "the work is the edit," not a new test.
- Always tag any surviving DB-backed test with `->group('integration-destructive')` and `IntegrationTestCase::skipIfUnavailable()` (or `TestConnection::skipIfUnavailable()`, the form used by most existing files).
- **User decision (locked):** retain exactly one real-SQL **descending + NULLs-last result-order** guard, consolidated into `EndToEndPriceSortOrderIntegrationTest` (task 006). The unit sort-spec test + ascending-only `InvariantMatrixTest` do not assert descending result ordering on real Postgres, so this single case stays.

## Risks & Mitigations
- **Hidden unique coverage in a "redundant" case** → each task lists the named equivalent; if absent, keep the case and note it rather than delete.
- **Relocation breaks `dirname()`/vendor paths** → discovery is glob-based and same-depth moves preserve `dirname`; recompute depth on deeper moves and run the package's suite after each move.
- **Parallel workers collide** → tasks scoped to disjoint files; 009 serializes verification.
- **Deleting a file leaves orphaned helpers / `Datasets.php` entries / `use` imports** → task 009 greps for dangling references and removes orphaned support files.
- **Removing tests drops coverage below the 80% gate** → these are integration tests over code also covered by unit tests; verify `--coverage --min=80` in task 009 and restore the minimal case if a real gap appears.
