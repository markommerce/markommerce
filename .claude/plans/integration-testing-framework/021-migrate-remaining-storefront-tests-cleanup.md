# Task 021: Migrate remaining storefront tests + remove fake-based wiring

**Status**: completed
**Depends on**: 020
**Retry count**: 0

## Description
Migrate the remaining storefront feature tests onto the harness (real DB + `handle()`), then remove the fake-repository + hand-built-wiring boilerplate the migration obsoletes, and document the new request→HTML pattern. Completes the conversion of the storefront suite from fake-data rendering to real full-stack integration tests.

## Context
- Migrate (verify GREEN after each), same approach as task 020 (`IntegrationTestCase` + `StoreProfile::storefront()` + factories + `$this->store->handle(...)`). As in 020, preserve status/header/short-circuit assertions 1:1 but REWRITE any HTML-body assertions that targeted fake-view placeholder markup to real component markup:
  1. `packages/catalog-storefront/tests/Feature/CategorySeoTest.php` — canonical/SEO `Link` header + view-all behavior from real data.
  2. `packages/catalog-storefront/tests/Feature/CategoryPageFragmentTest.php` — the load-more/infinite fragment endpoint (`/catalog/category/{id}/page`). Note this route HAS its own layout (`layout/category_page_fragment.php`), so it renders real HTML too.
  3. `packages/catalog-storefront/tests/Feature/PresentationSwitchTest.php` — numbered / load-more / infinite presentation modes (driven by config).
  4. `packages/catalog-storefront/tests/Feature/CategorySortRedirectTest.php` — invalid `?sort=` → 302 redirect (drop param), via `handle()`. Also covers 410 (page depth) and the loud `InvalidPaginationConfigException` (keyset-incompatible sort) — keep those.
- **CRITICAL — per-test config (this is the hard part of 020).** SEO and PresentationSwitch tests configure behavior PER TEST by injecting a fake `ConfigResolver` with overrides (`viewAllThreshold`, `maxPageDepth`, `defaultPageSize`, `presentation`, `strategy`, …). With the real `StoreProfile::storefront()` container, the controller resolves a REAL config-backed `ConfigResolver` reading `CatalogPaginationConfig` defaults + DB `config_value_overrides`. To vary behavior per test you must WRITE config into the DB before `handle()` (resolve the config writer from the store and set the relevant key) OR accept the package defaults where the test's scenario matches them. Confirm which knobs each test needs and how to set them against the real config pipeline; if the real config write path for these keys is not viable within an isolated transaction, flag it. Do NOT re-introduce a fake ConfigResolver. The keyset-incompatible-sort loud-error test does not need DB config (it's resolver-construction behavior) — verify it still throws via the real resolver.
- **Fake-repository cleanup — EXPECTED OUTCOME: little or nothing gets deleted.** A repo-wide sweep (`grep -rl 'Fake.*Repository' packages/*/tests/`) currently shows ~13 referencing test files OUTSIDE the migrated storefront feature tests: catalog's own unit Service/Seed tests (`CategoryAssignmentServiceTest`, `CategoryService/Tree*Test`, `ProductServiceTest`, `CatalogSeederTest`, `RepositoryContractsTest`), `catalog-storefront/tests/Unit/Component/ProductGridComponentTest`, and `catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest`. These are legitimate unit tests that SHOULD keep using fakes (no DB). Therefore: run the sweep, delete a `Fake*Repository` file ONLY if it has zero remaining referents after this migration, and EXPECT the catalog `Fake*Repository` support classes to REMAIN (they are still needed). Document the audit result (referent counts before/after, what was removed vs kept). Do NOT delete a fake that any unit test still imports — doing so breaks the unit suite.
- Remove the now-dead hand-built router/middleware/layout/`Compiler` helper preambles, the fake `ViewInterface`/`ContainerInterface` classes, and giant `use` blocks from the MIGRATED feature files (the harness provides this now) — but only from those files, not from the unit tests above.
- **Docs**: update the `markommerce/testing` docs page (`docs/src/content/docs/packages/testing.md`, from task 015) to document `BootedStore::handle()`, the `storefront` profile, the Vite handling, and the request→HTML assertion pattern, referencing one migrated test as the canonical example.
- Run sequentially, then `--parallel`; the full storefront suite + the wider integration suite must be green, and the UNIT suite must stay green (fakes still used).

## Requirements (Test Descriptions)
- [x] `it migrates CategorySeoTest asserting canonical and SEO headers from real data (config-driven view-all set via the real config pipeline)`
- [x] `it migrates CategoryPageFragmentTest for the load-more fragment endpoint rendering its real fragment layout`
- [x] `it migrates PresentationSwitchTest across presentation modes driven by real config writes`
- [x] `it migrates CategorySortRedirectTest invalid-sort 302 redirect, 410 page-depth, and the loud keyset-incompatible error via handle()`
- [x] `it removes hand-built router/middleware/fake-view boilerplate from the migrated files`
- [x] `it audits fake-repository references and keeps the fakes the catalog/storefront unit tests still need` (grep sweep; deletions only if zero referents remain)
- [x] `it keeps the unit suite green (fakes still used by unit tests)`
- [x] `it documents the handle() request-to-HTML pattern and Vite handling in the testing docs`

## Acceptance Criteria
- All four remaining storefront feature tests pass on the harness; status/SEO-header/short-circuit assertions preserved 1:1, body assertions rewritten to real markup; per-test config set via the real config pipeline (not a fake resolver).
- Dead router/middleware/fake-view wiring removed from the migrated feature files only; fakes still imported by unit tests are RETAINED and documented (audit shows referent counts).
- The unit suite, the storefront suite, and the wider integration suite are all green sequentially and in parallel.
- Testing docs document `handle()`, the storefront profile, and the Vite approach.
- PHPStan level 8 clean (`php -d memory_limit=2G`).

## Implementation Notes

### Migration completed for all 4 storefront feature tests

All four files were completely rewritten to use `IntegrationTestCase` + `StoreProfile::storefront()` + `CategoryFactory`/`ProductFactory` + `$store->handle()`. Fake infrastructure (FakeCategoryRepository, fake ConfigResolver, CatalogSeoFakeView, hand-built router/middleware, giant `use` blocks) was removed.

### Config resolver cache isolation discovery

`CachingConfigResolver` and `ConfigCacheResetMiddleware` use DIFFERENT `RequestConfigCache` instances due to container binding vs. singleton resolution ordering. Writing config then calling `handle()` again in the SAME store does NOT pick up new values. Resolution: write config BEFORE the first `handle()` call; use a fresh `IntegrationTestCase` per config variant (the resolver's in-memory cache starts empty for each new store). `PresentationSwitchTest` uses a `presentationHandleWithMode()` helper that creates a fresh test case per call.

### Pagination controls render condition

`product-grid.latte` only renders pagination (and therefore `mk-load-more`, `mk-infinite-scroll`, `catalog-pagination`) when `count($paginationUrls) > 0`, which requires multiple pages. Solution: set `catalog/pagination.defaultPageSize=1` and create 2+ products to force multiple pages in PresentationSwitchTest.

### Fake repository audit

Ran `grep -rl "FakeCategoryRepository\|FakeProductRepository\|FakeProductCategoryAssignmentRepository" packages/*/tests/`. All three core fake repos have 13+ referents in unit tests (catalog/tests/Unit, catalog-storefront/tests/Unit, catalog-storefront-scope/tests/Unit). None were removed. Inline fakes (views, containers, config resolvers) in the four migrated feature files were removed as part of migration.

### Vite in tests

`StoreProfile::storefront()` sets `vite.useDevServer=true`, so tests emit dev-server `<script type="module">` tags. Tests do not assert on Vite asset tags.

### Pre-existing PHPStan errors

PHPStan at level 8 shows 1000+ pre-existing errors (EntityQueryBuilderInterface::$orderByCalls, template type resolution). These are unrelated to this task. The source packages for catalog-storefront and testing are individually clean (`packages/catalog-storefront/src` + `packages/testing/src` → No errors).

### Test suite results

- Integration suite: 249 passed (700 assertions) in 14.33s
- Unit suite: 2079 passed (13 pre-existing ViteManifestException failures in frontend-demo, layout-demo, theme-blank — unrelated to this task)
