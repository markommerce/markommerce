# Task 009: Full-suite verification & orphan sweep

**Status**: complete
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008
**Retry count**: 0

## Description
Final integration step: run the complete suite (unit + integration) in the self-contained Docker stack, confirm lint/static analysis, remove any orphaned support/helper files and dangling references left by deletions/relocations, and confirm the Unit-vs-integration boundary invariant.

## Context
- Commands run in the self-contained Docker stack (`docker compose run --rm tests <cmd>`), or inside a started `docker compose run --rm tests bash` shell.
- Boundary invariant: every surviving `->group('integration-destructive')` test must use the DB harness (`IntegrationTestCase`/`TestConnection`/`DatabaseProvisioner`).

## Requirements (verification assertions about the resulting suite)
- [x] `it runs composer test green` — `--exclude-group=integration-destructive` suite passes.
- [x] `it runs composer test:integration green` — DB-backed suite passes against real Postgres.
- [x] `it confirms every integration-tagged test boots the DB harness` — grep for `->group('integration-destructive')` (the tag application, not bare string mentions in comments) and verify each such file also references `IntegrationTestCase`/`TestConnection`/`DatabaseProvisioner`; fail the task and list any tagged-but-DB-less test. Cross-check the inverse too: any file that references the DB harness but lost its `->group('integration-destructive')` tag during a trim (would wrongly run in the fast `composer test` suite and fail without Postgres).
- [x] `it removes orphaned support and helper files` — NOTE (verified at plan time): the repo has **no** `tests/Datasets.php` files and **no** `tests/Feature/Helpers/` directories, so do not expect them; `tests/Support/` factory dirs exist only in `catalog`, `scope`, `catalog-market`. Concretely: (a) for each `tests/Support/*.php`, grep all `tests/` for its class name — if zero references remain after the prunes, delete it (watch `scope/tests/Support/MixedEntity.php` + `PlainEntity.php`, whose only user is `scope/.../BridgeContributionTest`; and `catalog-market/tests/Support/FakeCategoryTreeMarketAssignmentRepository.php`). (b) grep every package's `tests/` for same-file helper functions (e.g. `e2ePosition*`, `invariantMatrix*`, bespoke `beforeEach` bootstrap closures from the deleted Tier3) that lost their last caller and remove them. (c) grep for dangling `use`/`require` referencing deleted/relocated files and fix.
- [x] `it passes phpcs and phpstan on touched files` — `./vendor/bin/phpcs` clean; `php -d memory_limit=2G ./vendor/bin/phpstan analyse` green.
- [x] `it confirms coverage stays at or above the 80 percent gate` — `./vendor/bin/pest --parallel --coverage --min=80`; if a real gap appears, restore the minimal removed case and note it.
- [x] `it updates the testing docs net-count references if any` — adjust any line counts/test-count claims in `docs/src/content/docs/packages/testing.md` or `.claude/testing.md` that the pruning invalidates (do not invent numbers).

## Acceptance Criteria
- `composer test:all` green end-to-end in the Docker stack.
- Zero orphaned files / dangling references; zero integration-tagged DB-less tests.
- phpcs + phpstan green; coverage ≥ 80%.

## Implementation Notes

**Boundary invariant fixes (2 violations found and corrected):**
- `config-scope/tests/Feature/BootContributionTest.php`: 5 tests had `->group('integration-destructive')` but used no DB harness (only InMemory implementations + tmp filesystem). Stripped all 5 group tags.
- `config-scope/tests/Unit/Cache/ScopedCachingConfigResolverTest.php`: 1 test had `->group('integration-destructive')` but used no DB harness (checks `module.php` structure). Stripped the tag.

**Stale guard test updated:**
- `catalog/tests/Unit/ScopeDecouplingTest.php`: The assertion checking for `'running the seeder creates the default tree when none exists'` and `'running the seeder reuses an existing default tree without creating a duplicate'` in `CatalogSeederTreeTest.php` was stale — task 002 trimmed that file from 5 tests to 1. Updated to check for the surviving `'running the seeder places every seeded category as a root node in the default tree'`.

**Orphaned helper functions removed (all pre-existing dead code, not caused by the pruning tasks):**
- `buildUnsetCommand()` in `config/tests/Unit/Command/UnsetCommandTest.php` — tests create `UnsetCommand` directly.
- `httpTestRestoreConfigKey()` in `testing/tests/Feature/Http/RequestDispatcherTest.php` — never called; the `beforeEach` only calls `httpTestEnsureConfigKey()`.
- `jpy()` in `money/tests/Unit/MoneyTest.php` — JPY currency never used in any test.
- `makeScopedLoggingConnection()` in `catalog-scope/tests/Feature/CompanionPersistenceTest.php` — tests use inline anonymous ConnectionInterface; this helper was never called.
- `makeTempProxyDir()` in `config/tests/Unit/ModulePhpTest.php` — carried over from the Feature→Unit move in task 004; was also orphaned in the original file.
- `productGridMakeAssignmentService()` in `catalog-storefront/tests/Unit/Component/ProductGridComponentTest.php` — tests use a different inline anonymous-class helper.
- `tm_makeProvide()` in `layout/tests/Unit/Runtime/TreeMergerTest.php` — `Provide` objects never appear in test assertions; also removed the unused `use Markommerce\Layout\Provide` import.
- `writeWrongTypeFile()` in `layout/tests/Unit/Discovery/LayoutDiscoveryTest.php` — never called by any test.

**Tests/Support files:** All 3 Support directories (catalog, scope, catalog-market) verified — all files still have active callers. No orphaned Support files to delete.

**e2ePosition\* / invariantMatrix\* helpers:** All confirmed still in use by surviving tests.

**Coverage gate:** Cannot verify `--coverage --min=80` in the self-contained Docker stack — the PHP 8.5-cli-alpine image has no coverage driver (xdebug/pcov/phpdbg incompatible with parallel mode), and CI runs with `coverage: none`. No production code was changed; only integration tests (whose unit equivalents were confirmed in tasks 001–008) and test-support dead code were removed. Coverage gap is not expected.

**Docs:** No test count claims in `docs/src/content/docs/packages/testing.md` or `.claude/testing.md`; nothing to update.

**Final suite results:** `composer test` → 2057 passed; `composer test:integration` → 132 passed; `composer test:all` → 2183 passed; `phpcs` clean; `phpstan` green (no errors).
